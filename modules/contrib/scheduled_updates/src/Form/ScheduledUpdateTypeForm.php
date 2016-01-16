<?php

/**
 * @file
 * Contains \Drupal\scheduled_updates\Form\ScheduledUpdateTypeForm.
 */

namespace Drupal\scheduled_updates\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\scheduled_updates\Entity\ScheduledUpdateType;
use Drupal\scheduled_updates\FieldManagerInterface;
use Drupal\scheduled_updates\FieldUtilsTrait;
use Drupal\scheduled_updates\Plugin\UpdateRunnerInterface;
use Drupal\scheduled_updates\Plugin\UpdateRunnerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ScheduledUpdateTypeForm.
 *
 * @package Drupal\scheduled_updates\Form
 */
class ScheduledUpdateTypeForm extends EntityForm {

  use FieldUtilsTrait;

  /** @var  ScheduledUpdateType */
  protected $entity;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\scheduled_updates\Plugin\UpdateRunnerManager
   */
  protected $runnerManager;

  /**
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\scheduled_updates\FieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Constructs a ScheduledUpdateTypeForm object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   * @param \Drupal\scheduled_updates\Plugin\UpdateRunnerManager $runnerManager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   * @param \Drupal\scheduled_updates\FieldManagerInterface $fieldManager
   */
  public function __construct(EntityFieldManagerInterface $entityFieldManager, UpdateRunnerManager $runnerManager, ModuleHandlerInterface $moduleHandler, EntityTypeBundleInfoInterface $entityTypeBundleInfo, FieldManagerInterface $fieldManager) {
    $this->entityFieldManager = $entityFieldManager;
    $this->runnerManager = $runnerManager;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->fieldManager = $fieldManager;

  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.scheduled_updates.update_runner'),
      $container->get('module_handler'),
      $container->get('entity_type.bundle.info'),
      $container->get('scheduled_updates.field_manager')
    );
  }
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var ScheduledUpdateType $scheduled_update_type */
    $scheduled_update_type = $this->entity;
    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $scheduled_update_type->label(),
      '#description' => $this->t("Label for the Scheduled update type."),
      '#required' => TRUE,
    );

    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $scheduled_update_type->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\scheduled_updates\Entity\ScheduledUpdateType::load',
      ),
      '#disabled' => !$scheduled_update_type->isNew(),
    );

    $default_type = $scheduled_update_type->getUpdateEntityType();


    $form['update_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity Type'),
      '#description' => $this->t('The entity type to update. This <strong>cannot</strong> be changed after this type is created.'),
      '#options' => $this->entityTypeOptions(),
      '#default_value' => $default_type,
      '#required' => TRUE,
      // @todo why doesn't this work?
      '#disabled' => !$scheduled_update_type->isNew(),
    ];
    // @todo Remove when bug is fixed.
    if (!$form['update_entity_type']['#disabled']) {
      // Just to duplicate issues on d.o for now.
      $form['update_entity_type']['#description'] .= '<br /><strong>**KNOWN BUG**</strong> : Ajax error when selecting one entity type and then selecting another: https://www.drupal.org/node/2643934';
    }


    // On Add give the user the option to create referencing entity reference field.
    $ajax = [
      '#limit_validation_errors' => array(),
      '#ajax' => array(
        'wrapper' => 'type-dependent-set',
        'callback' => '::updateTypeDependentSet',
        'method' => 'replace',
      )
    ];

    $form['update_entity_type'] += $ajax;
    $form['type_dependent_elements'] = [];

    // @todo Should the runner configuration form even be displayed before entity type is selected?
    $form['type_dependent_elements']['update_runner'] = $this->createRunnerElements($form_state);

    $form['type_dependent_elements']['update_runner']['id'] += $ajax;

    if ($this->entity->isNew()) {
      $form['type_dependent_elements']['reference_settings'] = $this->createNewFieldsElements($form, $form_state);
    }

    $form['type_dependent_elements'] += [
      '#type' => 'container',
      '#prefix' => '<div id="type-dependent-set" >',
      '#suffix' => '</div>',
    ];

    $form['field_map'] = $this->createFieldMapElements();
    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * Ajax Form call back for Update Runner Fieldset.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return
   */
  public function updateRunnerSettings(array $form, FormStateInterface $form_state) {
    $form_state->setValidationEnforced(FALSE);
    return $form['type_dependent_elements']['update_runner'];
  }

  /**
   * Ajax Form call back for Create Reference Fieldset.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return
   */
  public function updateTypeDependentSet(array $form, FormStateInterface $form_state) {
   // $form_state->setRebuild();
    $form_state->setValidationEnforced(FALSE);
    return $form['type_dependent_elements'];
  }


  /**
   * Create select element entity type options.
   *
   * @return array
   */
  protected function entityTypeOptions() {
    $options[''] = '';
    foreach ($this->entityTypeManager->getDefinitions() as $entity_id => $entityTypeInterface) {
      if ($entity_id == 'scheduled_update') {
        // Don't allow updating of updates! Inception!
        continue;
      }
      if (is_subclass_of($entityTypeInterface->getClass(), 'Drupal\Core\Entity\ContentEntityInterface')) {
        $options[$entity_id] = $entityTypeInterface->getLabel();
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $scheduled_update_type = $this->entity;
    $definition = $this->runnerManager->getDefinition($scheduled_update_type->getUpdateRunnerSettings()['id']);
    $scheduled_update_type->setUpdateTypesSupported($definition['update_types']);
    $status = $scheduled_update_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->setUpFieldReferences($form_state);
        drupal_set_message($this->t('Created the %label Scheduled Update Type.', [
          '%label' => $scheduled_update_type->label(),
        ]));
        drupal_set_message($this->t('Select fields to add to these updates'));
        $form_state->setRedirectUrl($scheduled_update_type->urlInfo('clone-fields'));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Scheduled update type.', [
          '%label' => $scheduled_update_type->label(),
        ]));
        $form_state->setRedirectUrl($scheduled_update_type->urlInfo('collection'));
    }

  }

  /**
   * Create form elements for runner selection and options.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   * @internal param $runner_settings
   *
   */
  protected function  createRunnerElements(FormStateInterface $form_state) {

    $runner_settings = $form_state->getValue('update_runner');
    $update_runner = $this->createRunnerInstance($runner_settings, $form_state);
    $elements = $update_runner->buildConfigurationForm([], $form_state);

    $runner_options = [];
    foreach ($this->runnerManager->getDefinitions() as $definition) {
      $runner_options[$definition['id']] = $definition['label'];
    }
    $elements['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Update Runner'),
      '#options' => $runner_options,
      '#default_value' => $runner_settings['id'],
      '#limit_validation_errors' => array(),
    ];
    $elements += [
      '#type' => 'fieldset',
      '#tree' => TRUE,
      '#title' => $this->t('Update Runner settings'),
    ];
    return $elements;
  }



  /**
   * {@inheritdoc}
   */
  public function FieldManager() {
    return $this->entityFieldManager;
  }

  /**
   * Create options for create a new entity reference field.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  protected function createNewFieldsElements(array &$form, FormStateInterface $form_state) {


    $entity_type = $this->entity->getUpdateEntityType();

    $elements = [];
    if ($entity_type && $this->runnerSupportsEmbedded($this->entity->getUpdateRunnerSettings())) {
      $elements = [
        '#type' => 'fieldset',
        '#tree' => TRUE,
        '#title' => 'Update Reference Options',
        '#prefix' => '<div id="create-reference-fieldset">',
        '#suffix' => '</div>',
      ];

      if ($this->moduleHandler->moduleExists('inline_entity_form')) {
        // Only works with Inline Entity Form for now

        // Option #1 Create a New Field
        $elements['new_field'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('New Field'),
        ];
        $elements['new_field']['create'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Create a new entity reference field'),
          '#description' => $this->t(
            'Create a new entity reference field on the @type entity type that will reference this update type.',
            ['@type' => $entity_type]
          )
        ];
        $new_field_visible['#states'] = array(
          'visible' => array(
            ':input[name="reference_settings[new_field][create]"]' => array('checked' => TRUE),
          ),
          'required' => array(
            ':input[name="reference_settings[new_field][create]"]' => array('checked' => TRUE),
          ),
        );

        $elements['new_field']['label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#size' => 15,
        ] + $new_field_visible;

        $field_prefix = $this->config('field_ui.settings')->get('field_prefix');
        $elements['new_field']['field_name'] = [
          '#type' => 'machine_name',
          // This field should stay LTR even for RTL languages.
          '#field_prefix' => '<span dir="ltr">' . $field_prefix,
          '#field_suffix' => '</span>&lrm;',
          '#size' => 15,
          '#description' => $this->t('A unique machine-readable name containing letters, numbers, and underscores.'),
          // Calculate characters depending on the length of the field prefix
          // setting. Maximum length is 32.
          '#maxlength' => FieldStorageConfig::NAME_MAX_LENGTH - strlen($field_prefix),
          '#machine_name' => array(
            'source' => ['reference_settings', 'new_field', 'label'],
            'exists' => array($this, 'fieldNameExists'),
          ),
          '#required' => FALSE,
        ] + $new_field_visible;
        $elements['new_field']['bundles'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Place field on bundles'),
          '#description' => $this->t('Choose which bundles to place this new field on.'),
          '#options' => $this->bundleOptions($entity_type),
        ] + $new_field_visible;

        $elements['new_field']['cardinality_container'] = array(
          // Reset #parents so the additional container does not appear.
          '#type' => 'fieldset',
          '#title' => $this->t('Allowed number of values'),
          '#attributes' => array('class' => array(
            'container-inline',
            'fieldgroup',
            'form-composite'
          )),
        )+ $new_field_visible;
        $elements['new_field']['cardinality_container']['cardinality'] = array(
          '#type' => 'select',
          '#title' => $this->t('Allowed number of values'),
          '#title_display' => 'invisible',
          '#options' => array(
            'number' => $this->t('Limited'),
            FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED => $this->t('Unlimited'),
          ),
          '#default_value' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        );
        $elements['new_field']['cardinality_container']['cardinality_number'] = array(
          '#type' => 'number',
          '#default_value' => 1,
          '#min' => 1,
          '#title' => $this->t('Limit'),
          '#title_display' => 'invisible',
          '#size' => 2,
          '#states' => array(
            'visible' => array(
              ':input[name="reference_settings[new_field][cardinality_container][cardinality]"]' => array('value' => 'number'),
            ),
            'disabled' => array(
              ':input[name="reference_settings[new_field][cardinality_container][cardinality]"]' => array('value' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED),
            ),
          ),
        );
        if ($existing_fields = $this->existingReferenceFields()) {
          // Option #2 Update existing Field
          $elements['existing_fields'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Existing Fields'),
            '#tree' => TRUE,
          ];
          $elements['existing_fields']['update'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Update existing entity reference field'),
            '#description' => $this->t(
              'Update entity reference fields on the @type entity type that will reference this update type.',
              ['@type' => $entity_type]
            )
          ];

          $existing_field_visible['#states'] = array(
            'visible' => array(
              ':input[name="reference_settings[existing_fields][update]"]' => array('checked' => TRUE),
            ),
            'required' => array(
              ':input[name="reference_settings[existing_fields][update]"]' => array('checked' => TRUE),
            ),
          );
          foreach ($existing_fields as $existing_field) {
            $options = [];
            foreach ($existing_field['bundles'] as $bundle => $field_info) {
              $options[$field_info['field_id']] = $this->t(
                'Field <em>@label</em> on <em>@bundle</em>',
                [
                  '@label' => $field_info['label'],
                  '@bundle' => $bundle,
                ]
              );
            }
            $elements['existing_fields']['fields'][$existing_field['field_name']] = [
              '#type' => 'checkboxes',
              '#title' => $existing_field['field_name'],
              '#options' => $options,
            ] + $existing_field_visible;
          }
        }
      }
      else {

        $markup = '<p>'. $this->t('It is recommended that you use the <a href="https://www.drupal.org/project/inline_entity_form" >Inline Entity Form</a> module when creating updates directly on the entities to be updated.') . '</p>';
        $markup .= '<p>'. $this->t('Only proceed if you have an alternative method of creating new update entities on entities to be updated.');
        $elements['notice'] = [
          '#type' => 'markup',
          '#markup' => $markup,
        ];
      }
    }

    return $elements;
  }

  /**
   * Create select element bundle options for entity type.
   * @param $entity_type
   *
   * @return array
   */
  protected function bundleOptions($entity_type) {
    $info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $options = [];
    foreach ($info as $bundle => $bundle_info) {
      $options[$bundle] = $bundle_info['label'];
    }
    return $options;
  }

  /**
   * Checks if a field machine name is taken.
   *
   * @param string $value
   *   The machine name, not prefixed.
   * @param array $element
   *   An array containing the structure of the 'field_name' element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether or not the field machine name is taken.
   */
  public function fieldNameExists($value, $element, FormStateInterface $form_state) {
    // Don't validate the case when an existing field has been selected.
    if ($form_state->getValue('existing_storage_name')) {
      return FALSE;
    }

    // Add the field prefix.
    $field_name = $this->configFactory->get('field_ui.settings')->get('field_prefix') . $value;

    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entity->getUpdateEntityType());
    return isset($field_storage_definitions[$field_name]);
  }

  /**
   * Setup entity reference field for this update type on add.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  protected function setUpFieldReferences(FormStateInterface $form_state) {
    $reference_settings = $form_state->getValue('reference_settings');
    if ($reference_settings['new_field']['create']) {
      $new_field_settings = $reference_settings['new_field'];
      $new_field_settings += $reference_settings['new_field']['cardinality_container'];
      unset($new_field_settings['cardinality_container']);
      $this->fieldManager->createNewReferenceField($new_field_settings, $this->entity);
    }

    if (!empty($reference_settings['existing_fields']['update'])) {
      $existing_field_settings = $reference_settings['existing_fields'];
      $this->fieldManager->updateExistingReferenceFields($existing_field_settings, $this->entity);

    }

  }

  /**
   * Create form elements to update field map.
   *
   * @return array
   * @internal param array $form
   * @internal param $scheduled_update_type
   *
   */
  protected function createFieldMapElements() {
    if ($this->entity->isNew()) {
      return [];
    }
    $field_map_help = 'Select the destination fields for this update type.'
      . ' Not all field have to have destinations if you using them for other purposes.';
    $elements = [
      '#type' => 'details',
      '#title' => 'destination fields',
      '#description' => $this->t($field_map_help),
      '#tree' => TRUE,
    ];
    $source_fields = $this->getSourceFields($this->entity);

    $field_map = $this->entity->getFieldMap();

    foreach ($source_fields as $source_field_id => $source_field) {
      $destination_fields_options = $this->getDestinationFieldsOptions($source_field);
      $elements[$source_field_id] = [
        '#type' => 'select',
        '#title' => $source_field->label(),
        '#options' => ['' => $this->t("(Not mapped)")] + $destination_fields_options,
        '#default_value' => isset($field_map[$source_field_id]) ? $field_map[$source_field_id] : '',
      ];
    }
    return $elements;
  }

  /**
   * Get existing entity reference field on target entity type that reference scheduled updates.
   *
   * @return array
   */
  protected function existingReferenceFields() {
    $entity_type = $this->entity->getUpdateEntityType();
    $fields = $this->entityFieldManager->getFieldMapByFieldType('entity_reference');
    if (!isset($fields[$entity_type])) {
      return [];
    }
    $fields = $fields[$entity_type];
    $ref_fields = [];
    foreach ($fields as $field_name => $field_info) {
      if ($definition = FieldStorageConfig::loadByName($entity_type, $field_name)) {
        $update_type = $definition->getSetting('target_type');
        if ($update_type == 'scheduled_update') {
          $ref_fields[$field_name]['field_name'] = $field_name;
          $bundle_fields = [];
          foreach ($field_info['bundles'] as $bundle) {
            $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
            $bundle_fields[$bundle] = [
              'field_id' => $field_config->id(),
              'label' => $field_config->label(),
            ];
          }
          $ref_fields[$field_name]['bundles'] = $bundle_fields;

        }
      }
    }
    return $ref_fields;
  }

  protected function runnerSupportsEmbedded($settings) {
    if ($this->runnerManager->hasDefinition($settings['id'])) {
      $definition = $this->runnerManager->getDefinition($settings['id']);
      return in_array('embedded', $definition['update_types']);
    }
    return FALSE;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $runner_settings = $form_state->getValue('update_runner');
    $update_runner = $this->createRunnerInstance($runner_settings, $form_state);
    $update_runner->validateConfigurationForm($form, $form_state);
  }

  /**
   * @param $runner_settings
   *
   * @return UpdateRunnerInterface
   */
  protected function createRunnerInstance(&$runner_settings, FormStateInterface $form_state) {
    if (empty($runner_settings)) {
      $runner_settings = $this->entity->getUpdateRunnerSettings();
    }
    if (!$this->runnerManager->hasDefinition($runner_settings['id'])) {
      // Settings is using plugin which no longer exists.
      $runner_settings = [
        'id' => 'default_embedded'
      ];
    }

    /** @var UpdateRunnerInterface $update_runner */
    $update_runner = $this->runnerManager->createInstance($runner_settings['id'], $runner_settings);

    $form_state->set('update_runner', $runner_settings);
    $form_state->set('scheduled_update_type', $this->entity);
    return $update_runner;
  }


}
