<?php

namespace Karls\DataMapperBundle\Command;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Unirest\Request as UnirestRequest;

/**
 * Class Data Mapping
 * @package Karls\DataMapperBundle\Command
 *
 * @usage
 * php bin/console karls:image-mapping
 *
 */
class ImageMappingCommand extends Command
{

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    /**
     * @var int
     */
    private $count;

    /**
     * {@inheritdoc}
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('karls:image-mapping')
            ->setDescription('Fixes images in S3 with no UUID')
    ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title($this->getName().' ('.$this->getDescription().')');

        $contentTypes = $this->getContentTypes();
        $contentType = $io->choice('Please select the content_type: ', $contentTypes);

        foreach ($contentTypes as $id => $type) {
            if ($type === $contentType) {
                $contentType = $id;
                break;
            }
        }

        $contents = $this->getContent($contentType);
        $contentTypeFields = $this->getContentTypeFields($contentType);
        $fields = $contentTypeFields['fields'];
        $settings = $contentTypeFields['settings'];

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Please select the type/s which to refactor: ', $fields);
        $question->setMultiselect(true);
        $fields = $helper->ask($input, $output, $question);

        foreach ($contents as $content) {
            $this->searchForFields($content->getData(), $fields, $settings);
        }

        $count = $this->count;
        if ($count > 0) {
            $io->success("Successfully fixed $count images on S3");
        } else {
            $io->success('Successful! There where no images to fix');
        }
    }


    private function searchForFields($array, $fields, $settings) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (in_array($key, $fields, false)) {
                    return $this->isImageValid($value, $settings[$key]);
                }
                return $this->searchForFields($value, $fields, $settings);
            }
        }
    }

    private function isImageValid($image, $setting) {
        $setting = $setting->__get('bucket');
        if (isset($setting['bucket'])) {
            $endpoint = $setting['endpoint'];
            $bucket = $setting['bucket'];
            $path = $setting['path'];
            $id = $image['id'];
            $name = $image['name'];
            $url = "$endpoint/$bucket/$path/$id/$name";
            try {
                $response = UnirestRequest::get($url);
                if ($response->code !== 200) {
                    $this->uploadFile("$bucket/$path/$name", $name, $id, $setting);
                }
                return true;
            } catch (\Exception $e) {
                echo $e;
            }
        }
        return false;
    }

    /**
     * @param int $contentType
     * @return array
     */
    private function getContent($contentType = null)
    {
        $queryBuilder = $this->em->getRepository('UniteCMSCoreBundle:Content')->createQueryBuilder('c');
        $query = $queryBuilder
            ->select('c')
            ->where('c.contentType = :contentType')
            ->setParameter('contentType', $contentType);

        $content = $query->getQuery()->getResult();

        return $content;
    }

    /**
     * @return array
     */
    private function getContentTypes()
    {
        $queryBuilder = $this->em->getRepository('UniteCMSCoreBundle:ContentType')->createQueryBuilder("content_type");
        $query = $queryBuilder
            ->select('c')
            ->from(\UniteCMS\CoreBundle\Entity\ContentType::class, 'c');

        $content = $query->getQuery()->getResult();

        $contentTypes = [];

        foreach ($content as $contentType) {
            $contentTypes[$contentType->getId()] = $contentType->getTitle();
        }

        return $contentTypes;
    }

    /**
     * @param $contentType int
     * @return array
     */
    private function getContentTypeFields($contentType)
    {
        $queryBuilder = $this->em->getRepository('UniteCMSCoreBundle:ContentTypeField')->createQueryBuilder("content_type");
        $query = $queryBuilder
            ->select('c')
            ->where('c.contentType = :contentType')
            ->setParameter('contentType', $contentType)
            ->from(\UniteCMS\CoreBundle\Entity\ContentTypeField::class, 'c');

        $content = $query->getQuery()->getResult();

        $contentTypeFields = [
            'fields' => [],
            'settings' => []
        ];

        foreach ($content as $contentTypeField) {
            $type = $contentTypeField->getType();
            if (!in_array($type, $contentTypeFields['fields'], false)) {
                $contentTypeFields['fields'][] = $type;
                $contentTypeFields['settings'][$type] = $contentTypeField->getSettings();
            }
        }

        return $contentTypeFields;
    }

    public function uploadFile($copyPath, $filename, $uuid, $bucket_settings)
    {
        $filenameparts = explode('.', $filename);
        if (count($filenameparts) < 2) {
            throw new \InvalidArgumentException(
                'Filename must include a file type extension.'
            );
        }

        $s3Config = [
            'version' => 'latest',
            'region' => $bucket_settings['region'] ?? 'us-east-1',
            'endpoint' => $bucket_settings['endpoint'],
            'use_path_style_endpoint' => true,
        ];

        if (isset($bucket_settings['key']) && isset($bucket_settings['secret'])) {
            $s3Config['credentials'] = [
                'key' => $bucket_settings['key'],
                'secret' => $bucket_settings['secret'],
            ];
        }

        $s3Client = new S3Client($s3Config);

        $filePath = $uuid.'/'.$filename;

        if (!empty($bucket_settings['path'])) {
            $path = trim($bucket_settings['path'], "/ \t\n\r\0\x0B");

            if (!empty($path)) {
                $filePath = $path.'/'.$filePath;
            }
        }

        try {
            $copyObjectParams = [
                'Bucket' => $bucket_settings['bucket'],
                'Key' => $filePath,
                'CopySource' => $copyPath
            ];
            $s3Client->copyObject($copyObjectParams);
            $this->count++;
        } catch (S3Exception $e) {
            echo $e->getMessage();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
