<?php

namespace Drupal\og\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\og\GroupTypeManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Group Type' block visibility condition.
 *
 * @Condition(
 *   id = "og_group_type",
 *   label = @Translation("Group type"),
 *   context = {
 *     "og" = @ContextDefinition("entity", label = @Translation("Group"))
 *   }
 * )
 */
class GroupType extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The group type manager.
   *
   * @var \Drupal\og\GroupTypeManager
   */
  protected $groupTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Creates a new GroupType instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\og\GroupTypeManager $group_type_manager
   *   The group type manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GroupTypeManager $group_type_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->groupTypeManager = $group_type_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('og.group_type_manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $group_types = $this->groupTypeManager->getAllGroupBundles();
    foreach ($group_types as $entity_type => $bundles) {
      $definition = $this->entityTypeManager->getDefinition($entity_type);
      $entity_type_label = $definition->getLabel();
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
      foreach ($bundles as $bundle) {
        $bundle_label = $bundle_info[$bundle]['label'];
        $options["$entity_type-$bundle"] = "$entity_type_label - $bundle_label";
      }
    }
    $form['group_types'] = [
      '#title' => $this->t('Group types'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $this->configuration['group_types'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['group_types'] = array_filter($form_state->getValue('group_types'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (count($this->configuration['group_types']) > 1) {
      $group_types = $this->configuration['group_types'];
      $last_group = array_pop($group_types);
      $group_types = implode(', ', $group_types);
      return $this->t('The group type is @group_types or @last_group', ['@group_types' => $group_types, '@last_group' => $last_group]);
    }
    $group_type = reset($this->configuration['group_types']);
    return $this->t('The group type is @group_type', ['@group_type' => $group_type]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration['group_types']) && !$this->isNegated()) {
      return TRUE;
    }
    $group = $this->getContextValue('og');
    $key = $group->getEntityTypeId() . '-' . $group->bundle();
    return !empty($this->configuration['group_types'][$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['group_types' => []] + parent::defaultConfiguration();
  }

}
