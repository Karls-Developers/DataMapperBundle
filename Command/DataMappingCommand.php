<?php

namespace Karls\DataMapperBundle\Command;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Class Data Mapping
 * @package Karls\DataMapperBundle\Command
 *
 * @usage
 * php bin/console karls:data-mapping
 *
 */
class DataMappingCommand extends Command
{

  /**
   * @var \Doctrine\ORM\EntityManager
   */
  private $em;

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
      ->setName('karls:data-mapping')
      ->setDescription('Map old JSON structure with new JSON structure');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $output->writeln([
      '<info>#################################</info>',
      '<info># Data Mapping Command Executed #</info>',
      '<info>#################################</info>',
    ]);
    $contentType = $this->getContentType("jobs");
    $data = $this->getContent($contentType);
    $data_count = count($data);

    if ($data_count === 0) {
      $output->writeln([
        '<comment>No Data Fetched.</comment>'
      ]);
      return;
    }
    $output->writeln([
      '<comment>Fetched ' . $data_count . ' Data(s)</comment>'
    ]);

    $output->writeln('<info>Start Rewrite JSON</info>');

    $progressBar = new ProgressBar($output, $data_count);
    $progressBar->setFormat('%current%/%max% [%bar%] %percent%%    %elapsed%');
    $progressBar->setBarCharacter('<comment>=</comment>');
    $progressBar->setEmptyBarCharacter(' ');
    $progressBar->setProgressCharacter('>');
    $progressBar->setBarWidth(50);
    $progressBar->start();

    for ($i = 0; $i < $data_count; $i++) {
      $a = $data[$i]->getData();
      $decription_count = count($a['description']);
      for ($j = 0; $j < $decription_count; $j++) {
        $b = $a['description'][$j];
        if (!array_key_exists('text_type', $b)) {
          $b = $this->addAndInsertInNewKey($b, 'text_type', null, true);
          $b['text_type'] = $this->addAndInsertInNewKey($b['text_type'], 'type', 'headline_text');
          $b['text_type'] = $this->addAndInsertInNewKey($b['text_type'], 'headline_text', null, true);
          if (array_key_exists('content', $b)) {
            $b['text_type']['headline_text'] = $this->addAndInsertInNewKey($b['text_type']['headline_text'], 'content',
              $b['content']);
            unset($b['content']);
          }
          if (array_key_exists('headline', $b)) {
            $b['text_type']['headline_text'] = $this->addAndInsertInNewKey($b['text_type']['headline_text'], 'headline',
              $b['headline']);
            unset($b['headline']);
          }
          $a['description'][$j] = $b;
          $data[$i]->setData($a);
          $this->em->persist($data[$i]);
        }
      }
      $progressBar->advance();
    }
    $this->em->flush();
    $progressBar->finish();
    $output->writeln('');
  }


  /**
   * @param array $array
   * @param string $new_key
   * @param mixed $value
   * @param bool $new_key_is_array
   * @return mixed
   */
  private function addAndInsertInNewKey($array, $new_key, $value, $new_key_is_array = false)
  {
    if ($this->checkIfKeyIsArray($new_key, $array)) {
      $new_key_is_array = true;
    }
    if ($new_key_is_array) {
      if ($this->checkIfKeyIsArray($new_key, $array)) {
        array_push($array[$new_key], $value);
      } else {
        if (!empty($value)) {
          $array[$new_key] = [$value];
        } else {
          $array[$new_key] = [];
        }
      }
    } else {
      $array[$new_key] = $value;
    }
    return $array;
  }

  /**
   * @param string $key
   * @param array $array
   * @return bool
   */
  private function checkIfKeyIsArray($key, $array)
  {
    return (array_key_exists($key, $array) && is_array($array[$key]));
  }

  /**
   * @param int $contentType
   * @return array
   */
  private function getContent($contentType = null)
  {
    $queryBuilder = $this->em->getRepository('UniteCMSCoreBundle:Content')->createQueryBuilder('c');
    $query = $queryBuilder
      ->select("c")
      ->where("c.contentType = :contentType")
      ->setParameter('contentType', $contentType);

    $content = $query->getQuery()->getResult();
    return $content;
  }

  /**
   * @param string $identifier Name of the Identifier
   * @return array
   */
  private function getContentType($identifier)
  {
    $queryBuilder = $this->em->getRepository('UniteCMSCoreBundle:ContentType')->createQueryBuilder("content_type");
    $query = $queryBuilder
      ->select("c")
      ->from(\UniteCMS\CoreBundle\Entity\ContentType::class, "c")
      ->where("c.identifier = :identifier ")
      ->setParameter('identifier', $identifier);
    $query->setMaxResults(1);

    $content = $query->getQuery()->getResult();
    return $content[0]->getId();
  }
}
