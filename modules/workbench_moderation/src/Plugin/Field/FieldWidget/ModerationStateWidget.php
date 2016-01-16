<?php

/**
 * @file
 * Contains \Drupal\workbench_moderation\Plugin\Field\FieldWidget\ModerationStateWidget.
 */

namespace Drupal\workbench_moderation\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workbench_moderation\Entity\ModerationState;
use Drupal\workbench_moderation\ModerationInformation;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'moderation_state_default' widget.
 *
 * @FieldWidget(
 *   id = "moderation_state_default",
 *   label = @Translation("Moderation state"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ModerationStateWidget extends OptionsSelectWidget implements ContainerFactoryPluginInterface {

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Moderation state transition entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $moderationStateTransitionEntityQuery;

  /**
   * Moderation state storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $moderationStateStorage;

  /**
   * @var \Drupal\workbench_moderation\ModerationInformation
   */
  protected $moderationInformation;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $moderationStateTransitionStorage;

  /**
   * Constructs a new ModerationStateWidget object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $moderation_state_storage
   *   Moderation state storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $moderation_state_transition_storage
   *   Moderation state transition storage.
   * @param \Drupal\Core\Entity\Query\QueryInterface $entity_query
   *   Moderation transation entity query service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, EntityStorageInterface $moderation_state_storage, EntityStorageInterface $moderation_state_transition_storage, QueryInterface $entity_query, ModerationInformation $moderation_information) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->moderationStateTransitionEntityQuery = $entity_query;
    $this->moderationStateTransitionStorage = $moderation_state_transition_storage;
    $this->moderationStateStorage = $moderation_state_storage;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->moderationInformation = $moderation_information;
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
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.manager')->getStorage('moderation_state'),
      $container->get('entity_type.manager')->getStorage('moderation_state_transition'),
      $container->get('entity.query')->get('moderation_state_transition', 'AND'),
      $container->get('workbench_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $bundle_entity */
    $bundle_entity = $this->entityTypeManager->getStorage($entity->getEntityType()->getBundleEntityType())->load($entity->bundle());
    if (!$this->moderationInformation->isModeratableEntity($entity)) {
      // @todo write a test for this.
      return $element + ['#access' => FALSE];
    }
    $options = $this->fieldDefinition
      ->getFieldStorageDefinition()
      ->getOptionsProvider($this->column, $entity)
      ->getSettableOptions($this->currentUser);

    $default = $items->get($delta)->target_id ?: $bundle_entity->getThirdPartySetting('workbench_moderation', 'default_moderation_state', FALSE);
    /** @var \Drupal\workbench_moderation\ModerationStateInterface $default_state */
    $default_state = ModerationState::load($default);
    if (!$default || !$default_state) {
      throw new \UnexpectedValueException(sprintf('The %s bundle has an invalid moderation state configuration, moderation states are enabled but no default is set.', $bundle_entity->label()));
    }
    // @todo write a test for this.
    $from = $this->moderationStateTransitionEntityQuery
      ->condition('stateFrom', $default)
      ->execute();
    // Can always keep this one as is.
    $to[$default] = $default;
    // @todo write a test for this.
    $allowed = $bundle_entity->getThirdPartySetting('workbench_moderation', 'allowed_moderation_states', []);
    if ($from) {
      /* @var \Drupal\workbench_moderation\ModerationStateTransitionInterface $transition */
      foreach ($this->moderationStateTransitionStorage->loadMultiple($from) as $id => $transition) {
        $to_state = $transition->getToState();
        if ($this->currentUser->hasPermission('use ' . $id . ' transition') && in_array($to_state, $allowed, TRUE)) {
          $to[$to_state] = $to_state;
        }
      }
    }
    $options = array_intersect_key($options, $to);

    // @todo write a test for this.
    $element += [
      '#access' => count($options),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $default,
      '#published' => $default ? $default_state->isPublishedState() : FALSE,
    ];
    if ($this->currentUser->hasPermission($this->getAdminPermission($entity->getEntityType())) && count($options)) {
      // Use the dropbutton.
      $element['#process'][] = [get_called_class(), 'processActions'];
      // Don't show in sidebar/body.
      $element['#access'] = FALSE;
    }
    else {
      // Place the field as a details element in the advanced tab-set in e.g.
      // the sidebar.
      $element = [
        '#type' => 'details',
        '#group' => 'advanced',
        '#open' => TRUE,
        '#weight' => -10,
        '#title' => t('Moderation state'),
        'target_id' => $element,
      ];
    }

    return $element;
  }

  protected function getAdminPermission(EntityTypeInterface $entity_type) {
    switch ($entity_type->id()) {
      case 'node':
        return 'administer nodes';
      default:
        return $entity_type->getAdminPermission();
    }
  }

  /**
   * Entity builder updating the node moderation state with the submitted value.
   *
   * @param string $entity_type_id
   *   The entity type identifier.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity updated with the submitted values.
   * @param array $form
   *   The complete form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function updateStatus($entity_type_id, ContentEntityInterface $entity, array $form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    if (isset($element['#moderation_state'])) {
      $entity->moderation_state->target_id = $element['#moderation_state'];
    }
  }
  /**
   * Process callback to alter action buttons.
   */
  public static function processActions($element, FormStateInterface $form_state, array &$form) {
    $default_button = $form['actions']['submit'];
    $default_button['#access'] = TRUE;
    $options = $element['#options'];
    foreach ($options as $id => $label) {
      if ($id === $element['#default_value']) {
        if ($element['#published']) {
          $button = [
            '#value' => t('Save and keep @state', ['@state' => $label]),
            '#dropbutton' => 'save',
            '#moderation_state' => $id,
            '#weight' => -10,
          ];
        }
        else {
          $button = [
            '#value' => t('Save as @state', ['@state' => $label]),
            '#dropbutton' => 'save',
            '#moderation_state' => $id,
            '#weight' => -10,
          ];
        }
      }
      else {
        // @todo write a test for this.
        $button = [
          '#value' => t('Save and @type @state', [
            '@state' => $label,
            '@type' => $element['#published'] ? t('create new revision in') : t('transition to'),
          ]),
          '#dropbutton' => 'save',
          '#moderation_state' => $id,
        ];
      }
      $form['actions']['moderation_state_' . $id] = $button + $default_button;
    }
    foreach (['publish', 'unpublish'] as $key) {
      $form['actions'][$key]['#access'] = FALSE;
      unset($form['actions'][$key]['#dropbutton']);
    }
    $form['#entity_builders']['update_moderation_state'] = [get_called_class(), 'updateStatus'];
    return $element;
  }


  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return parent::isApplicable($field_definition) && $field_definition->getName() === 'moderation_state';
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();

    // Extract the values from $form_state->getValues().
    $path = array_merge($form['#parents'], array($field_name));
    $key_exists = NULL;
    // Convert the field value into expected array format.
    $values = $form_state->getValues();
    $value = NestedArray::getValue($values, $path, $key_exists);
    if (empty($value)) {
      parent::extractFormValues($items, $form, $form_state);
      return;
    }
    if (!isset($value[0]['target_id'])) {
      NestedArray::setValue($values, $path, [['target_id' => reset($value)]]);
      $form_state->setValues($values);
    }
    parent::extractFormValues($items, $form, $form_state);
  }

}
