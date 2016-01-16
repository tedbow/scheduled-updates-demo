<?php
/**
 * @file
 * Contains \Drupal\scheduled_updates\FieldManager.
 */


namespace Drupal\scheduled_updates;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Field Manager for handling fields for Scheduled Updates.
 *
 */
class FieldManager implements FieldManagerInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * UpdateRunner constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, ConfigFactoryInterface $config_factory) {
    $this->entityFieldManager = $entityFieldManager;
    $this->configFactory = $config_factory;

  }

  /**
   * [@inheritdoc}
   */
  public function cloneField(ScheduledUpdateTypeInterface $scheduled_update_type, $field_name, $field_config_id = NULL) {
    $entity_type = $scheduled_update_type->getUpdateEntityType();
    $definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type);
    if (!isset($definitions[$field_name])) {
      return FALSE;
    }
    $definition = $definitions[$field_name];

    $new_field_name = $this->getNewFieldName($definition);
    $field_storage_values = [
      'field_name' => $new_field_name,
      'entity_type' => 'scheduled_update',
      'type' => $definition->getType(),
      'translatable' => $definition->isTranslatable(),
      'settings' => $definition->getSettings(),
      'cardinality' => $definition->getCardinality(),
     // 'module' => $definition->get @todo how to get module
    ];
    $field_values = [
      'field_name' => $new_field_name,
      'entity_type' => 'scheduled_update',
      'bundle' => $scheduled_update_type->id(),
      'label' => $definition->getLabel(),
      // Field translatability should be explicitly enabled by the users.
      'translatable' => FALSE,
    ];
    /** @var FieldConfig $field_config */
    if ($field_config_id && $field_config = FieldConfig::load($field_config_id)) {
      $field_values['settings'] = $field_config->getSettings();
      $field_values['label'] = $field_config->label();
    }

    // @todo Add Form display settings!

    FieldStorageConfig::create($field_storage_values)->save();
    $field = FieldConfig::create($field_values);
    $field->save();

    $destination_bundle = $scheduled_update_type->id();
    /** @var EntityFormDisplay $destination_form_display */
    $destination_form_display = EntityFormDisplay::load("scheduled_update.$destination_bundle.default");
    if (empty($destination_form_display)) {
      $destination_form_display = EntityFormDisplay::create([
        'targetEntityType' => 'scheduled_update',
        'bundle' => $destination_bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $display_options = [];
    if ($field_config_id) {
      $parts = explode('.', $field_config_id);
      $source_bundle = $parts[1];
      /** @var EntityFormDisplay $source_form_display */
      $source_form_display = EntityFormDisplay::load("$entity_type.$source_bundle.default");

      $display_options = $source_form_display->getComponent($field_name);
    }
    else {
      if ($definition instanceof BaseFieldDefinition) {
        $display_options = $definition->getDisplayOptions('form');
        if (empty($display_options)) {
          if ($definition->getType()) {
            // Provide default display for base boolean fields that don't have their own form display
            $display_options = [
              'type' => 'boolean_checkbox',
              'settings' => ['display_label' => TRUE],
            ];
          }
        }
      }
    }
    if (empty($display_options)) {
      $display_options = [];
    }
    if ($destination_form_display) {
      $destination_form_display->setComponent($new_field_name, $display_options);
      $destination_form_display->save();
    }
    else {
      // Alert user if display options could not be created.
      // @todo Create default display options even none on source.
      drupal_set_message(
        $this->t(
          'Form display options could not be created for @label they will have to be created manually.',
          ['@label' => $field_values['label']]
        ),
        'warning');
    }


    return $field;
  }

  protected function createDefinition(FieldStorageDefinitionInterface $definition) {

  }

  /**
   * Gets the first available field name for a give source field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *
   * @return string
   * @internal param \Drupal\scheduled_updates\ScheduledUpdateTypeInterface $scheduled_update_type
   *
   */
  protected function getNewFieldName(FieldStorageDefinitionInterface $definition) {
    $field_name = $definition->getName();
    if ($definition->isBaseField()) {
      $field_name = $this->configFactory->get('field_ui.settings')->get('field_prefix') . $field_name;
    }
    $suffix = 0;
    $new_field_name = $field_name;
    while($this->fieldNameExists($new_field_name, 'scheduled_update')) {
      $suffix++;
      $new_field_name = $field_name . '_' . $suffix;
    }
    return $new_field_name;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldNameExists($field_name, $entity_type_id) {
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    return isset($field_storage_definitions[$field_name]);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllFieldConfigsForField(FieldStorageDefinitionInterface $definition, $entity_type_id) {
    $map = $this->entityFieldManager->getFieldMap()[$entity_type_id];
    $definitions = [];
    $field_name = $definition->getName();
    if (isset($map[$field_name])) {
      $bundles = $map[$field_name]['bundles'];
      foreach ($bundles as $bundle) {
        $bundle_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
        $definitions[$bundle] = $bundle_definitions[$field_name];
      }
    }

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewReferenceField(array $new_field_settings, ScheduledUpdateTypeInterface $scheduled_update_type) {
    $entity_type = $scheduled_update_type->getUpdateEntityType();
    $field_name = $new_field_settings['field_name'];
    $label = $new_field_settings['label'];
    if ($new_field_settings['cardinality'] == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
      $new_field_settings['cardinality_number'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
     }
    $field_storage_values = [
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'entity_reference',
      'translatable' => FALSE,
      'settings' => ['target_type' => 'scheduled_update'],
      'cardinality' => $new_field_settings['cardinality_number'], // @todo Add config to form
      // 'module' => $definition->get @todo how to get module
    ];
    FieldStorageConfig::create($field_storage_values)->save();
    $bundles = array_filter($new_field_settings['bundles']);
    foreach ($bundles as $bundle) {
      $field_values = [
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $label,
        // Field translatability should be explicitly enabled by the users.
        'translatable' => FALSE,
        'settings' => [
          'handler_settings' => [
            'target_bundles' => [$scheduled_update_type->id()],
          ],
        ],
      ];
      $field = FieldConfig::create($field_values);
      $field->save();

      /** @var EntityFormDisplay $formDisplay */
      $formDisplay = EntityFormDisplay::load("$entity_type.$bundle.default");
      $form_options = [
        'type' => 'inline_entity_form_complex',
        'weight' => '11',
        'settings' => [
          'override_labels' => TRUE,
          'label_singular' => $label,
          'label_plural' => $label . 's',
          'allow_new' => TRUE,
          'match_operator' => 'CONTAINS',
          'allow_existing' => FALSE,
          ],
      ];
      $formDisplay->setComponent($field_name, $form_options);
      $formDisplay->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateExistingReferenceFields(array $existing_field_settings, ScheduledUpdateTypeInterface $scheduled_update_type) {
    $fields = $existing_field_settings['fields'];
    foreach ($fields as $field_ids) {
      $field_ids = array_filter($field_ids);
      foreach ($field_ids as $field_id) {
        /** @var FieldConfig $field_config */
        $field_config = FieldConfig::load($field_id);
        $settings = $field_config->getSetting('handler_settings');
        $settings['target_bundles'][] = $scheduled_update_type->id();
        $field_config->setSetting('handler_settings', $settings);
        $field_config->save();
      }
    }

  }

}
