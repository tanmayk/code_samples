<?php

namespace Drupal\ex_tests\Testing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Create any type of entity.
 */
class EntityCreateManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Contructs EntityCreateManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteBuilderInterface $route_builder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->routeBuilder = $route_builder;
  }

  /**
   * Creates an entity based on provided values.
   *
   * @param string $entity_type
   *   The type of entity to create.
   * @param array $values
   *   An array of values to use for entity.
   *   Example: 'type' => 'foo'.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Created entity.
   */
  public function createEntity($entity_type, array $values = []) {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity = $storage->create($values);
    $status = $entity->save();
    // Rebuild the routes.
    $this->routeBuilder->rebuild();
    return $entity;
  }

}
