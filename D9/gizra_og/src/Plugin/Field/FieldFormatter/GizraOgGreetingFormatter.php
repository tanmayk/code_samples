<?php

namespace Drupal\gizra_og\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for the OG subscribe formatter.
 *
 * @FieldFormatter(
 *   id = "gizra_og_greeting",
 *   label = @Translation("Group Subscription Greeting"),
 *   description = @Translation("Display Group subscription greeting text."),
 *   field_types = {
 *     "og_group"
 *   }
 * )
 */
class GizraOgGreetingFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The OG access service.
   *
   * @var \Drupal\og\OgAccessInterface
   */
  protected $ogAccess;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new GroupSubscribeFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountInterface $current_user, OgAccessInterface $og_access, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->currentUser = $current_user;
    $this->ogAccess = $og_access;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('og.access'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Cache by the membership state.
    $elements['#cache']['contexts'] = [
      'og_membership_state',
      'user.roles:authenticated',
    ];
    $cache_meta = CacheableMetadata::createFromRenderArray($elements);

    $group = $items->getEntity();
    $entity_type_id = $group->getEntityTypeId();
    $cache_meta->merge(CacheableMetadata::createFromObject($group));
    $cache_meta->applyTo($elements);

    $user = $this->entityTypeManager->getStorage('user')->load(($this->currentUser->id()));
    if (($group instanceof EntityOwnerInterface) && ($group->getOwnerId() == $user->id())) {
      // User is the group manager. Nothing to show.
      return $elements;
    }

    // Check if user already has membership.
    $storage = $this->entityTypeManager->getStorage('og_membership');
    $props = [
      'uid' => $user ? $user->id() : 0,
      'entity_type' => $group->getEntityTypeId(),
      'entity_bundle' => $group->bundle(),
      'entity_id' => $group->id(),
    ];
    $memberships = $storage->loadByProperties($props);
    $membership = reset($memberships);

    if ($membership) {
      // Found membership. Nothing to show.
      $cache_meta->merge(CacheableMetadata::createFromObject($membership));
      $cache_meta->applyTo($elements);
    }
    else {
      // If the user is authenticated, & has enough access,
      // show the greeting text.
      $subscribe_no_approval = $this->ogAccess->userAccess($group, 'subscribe', $user)->isAllowed();
      $subscribe = $this->ogAccess->userAccess($group, 'subscribe', $user)->isAllowed();
      if ($user->isAuthenticated() && ($subscribe_no_approval || $subscribe)) {
        // Prepare the url.
        $parameters = [
          'entity_type_id' => $group->getEntityTypeId(),
          'group' => $group->id(),
          'og_membership_type' => OgMembershipInterface::TYPE_DEFAULT,
        ];
        $url = Url::fromRoute('og.subscribe', $parameters);

        $elements[0] = [
          '#theme' => 'gizra_og_greeting',
          '#name' => $user->getAccountName(),
          '#url' => $url->toString(),
          '#group_label' => $group->getTitle(),
        ];
      }
      else {
        $cache_meta->setCacheContexts(['user.roles:anonymous']);
        $cache_meta->applyTo($elements);
      }
    }
    return $elements;
  }

}
