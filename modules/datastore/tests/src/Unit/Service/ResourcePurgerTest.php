<?php

namespace Drupal\Tests\datastore\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\datastore\Service;
use Drupal\datastore\Service\ResourcePurger;
use Drupal\metastore\Storage\AbstractEntityStorage;
use Drupal\metastore\Storage\MetastoreDataNodeStorage;
use Drupal\metastore\Storage\MetastoreDataNodeStorageFactory;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use MockChain\Chain;
use MockChain\Options;
use PHPUnit\Framework\TestCase;

/**
 * Class ResourcePurgerTest
 *
 * @package Drupal\Tests\datastore\Service
 */
class ResourcePurgerTest extends TestCase {

  /**
   * Test when neither purge config is set.
   */
  public function testNoPurgeConfig() {

    $chain = $this->getCommonChain()
      ->add(ImmutableConfig::class, 'get', 0);

    $resourcePurger = ResourcePurger::create($chain->getMock());
    $voidResult = $resourcePurger->schedule([], FALSE, TRUE);
    $this->assertNull($voidResult);
  }

  /**
   * Test queueing the purge.
   */
  public function testQueueing() {

    $chain = $this->getCommonChain()
      ->add(Service::class, 'getQueueFactory', QueueFactory::class)
      ->add(QueueFactory::class, 'get', QueueInterface::class)
      ->add(QueueInterface::class, 'createItem', 1);

    $resourcePurger = ResourcePurger::create($chain->getMock());
    $voidResult = $resourcePurger->schedule([], TRUE);
    $this->assertNull($voidResult);
  }

  /**
   * Test dataset revision disappearing between the queueing and processing.
   */
  public function testRevisionDisappearing() {

    $chain = $this->getCommonChain()
      ->add(MetastoreDataNodeStorage::class, 'getEntityIdFromUuid', 1)
      ->add(MetastoreDataNodeStorage::class, 'getEntityStorage', NodeStorageInterface::class)
      ->add(NodeStorageInterface::class, 'getLatestRevisionId', 1);

    $resourcePurger = ResourcePurger::create($chain->getMock());
    $voidResult = $resourcePurger->schedule([1], FALSE);
    $this->assertNull($voidResult);
  }

  /**
   * Test the function called by dkan:datastore:purge-all.
   */
  public function testScheduleAllUuids() {

    $chain = $this->getCommonChain()
      ->add(MetastoreDataNodeStorage::class, 'getEntityIdFromUuid', 1)
      ->add(MetastoreDataNodeStorage::class, 'getEntityStorage', NodeStorageInterface::class)
      ->add(NodeStorageInterface::class, 'getQuery', QueryInterface::class)
      ->add(QueryInterface::class, 'condition', QueryInterface::class)
      ->add(QueryInterface::class, 'execute', [1])
      ->add(NodeStorageInterface::class, 'load', Node::class)
      ->add(Node::class, 'uuid', 'foo')
      ->add(ImmutableConfig::class, 'get', 0);

    $resourcePurger = ResourcePurger::create($chain->getMock());
    $voidResult = $resourcePurger->scheduleAllUuids();
    $this->assertNull($voidResult);
  }

  /**
   * Get common chain.
   */
  private function getCommonChain() {

    $options = (new Options())
      ->add('config.factory', ConfigFactoryInterface::class)
      ->add('dkan.metastore.storage', MetastoreDataNodeStorageFactory::class)
      ->add('dkan.datastore.service', Service::class)
      ->index(0);

    return (new Chain($this))
      ->add(Container::class, 'get', $options)
      ->add(ConfigFactoryInterface::class, 'get', ImmutableConfig::class)
      ->add(ImmutableConfig::class, 'get', 1)
      ->add(MetastoreDataNodeStorageFactory::class, 'getInstance', MetastoreDataNodeStorage::class);
  }

}