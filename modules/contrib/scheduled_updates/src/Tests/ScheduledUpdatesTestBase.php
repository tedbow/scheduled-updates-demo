<?php
/**
 * @file
 * Contains \Drupal\scheduled_updates\Tests\ScheduledUpdatesTestBase.
 */

namespace Drupal\scheduled_updates\Tests;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\scheduled_updates\Plugin\UpdateRunnerInterface;
use Drupal\simpletest\WebTestBase;

/**
 * Define base class for Scheduled Updates Tests
 */
abstract class ScheduledUpdatesTestBase extends WebTestBase {

  /**
   * Profile to use.
   */
  protected $profile = 'testing';

  /**
   * Admin user
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access administration pages',
    'administer content types',
    'administer nodes',
    'administer scheduled update types',
    'administer scheduled_update fields',
    'administer scheduled_update form display',
  ];

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'scheduled_updates',
    'node',
    'user',
    'field_ui',
    'block',
  ];


  /**
   * Sets the test up.
   */
  protected function setUp() {
    parent::setUp();
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'tabs_block']);
    $this->drupalPlaceBlock('page_title_block');
    $this->drupalPlaceBlock('local_actions_block', ['id' => 'actions_block']);
  }

  /**
   * Utility Function around drupalGet to avoid call if not needed.
   *
   * @param $path
   */
  protected function gotoURLIfNot($path) {
    if ($path != $this->getUrl()) {
      $this->drupalGet($path);
    }
  }

  protected function cloneFields($type_id, array $fields) {
    $this->gotoURLIfNot("admin/config/workflow/scheduled-update-type/$type_id/clone-fields");
    $edit = [];
    foreach ($fields as $input_name => $field_info) {
      // Check the field label exists.
      $this->assertText(
        $field_info['label'],
        new FormattableMarkup('Field label %label displayed.', ['%label' => $field_info['label']])
      );
      // Add to post data.
      $edit[$input_name] = $field_info['input_value'];
    }
    $this->drupalPostForm(NULL, $edit, t('Clone Fields'));
    if ($this->adminUser->hasPermission('administer scheduled_update form display')) {
      // Should be redirected to form display after cloning fields
      $this->assertUrl("admin/config/workflow/scheduled-update-type/$type_id/form-display");
      $this->checkFieldLabels($fields);
    }
    else {
      // @todo Does it make any sense for admin to be able to add update types without Field UI permissions
      //  Enforce Field UI permissions to add scheduled update type?
      $this->assertText('You do not have permission to administer fields on Scheduled Updates.');
    }

  }

  /**
   * @param array $fields
   */
  protected function checkFieldLabels(array $fields) {
    foreach ($fields as $input_name => $field_info) {
      // We only know what base field labels should look like.
      if (stripos($input_name, 'base_fields[') === 0) {
        // Check the field label exists.
        $this->assertText(
          $field_info['label'],
          new FormattableMarkup('Field label %label displayed.', ['%label' => $field_info['label']])
        );
      }
      else {
        // @test that Configurable fields were cloned.
      }
    }
  }

  protected function checkNodeProperties() {
    $property_labels = [
      'Language',
      'Title',
      'Authored by',
      'Publishing status',
      'Authored on',
      'Changed',
      'Promoted to front page',
      'Sticky at top of lists',
      'Revision timestamp',
      'Revision user ID',
      'Revision log message',
      'Default translation',
    ];
    foreach ($property_labels as $property_label) {
      $this->assertText($property_label);
    }

  }

  /**
   * @param $label
   * @param $id
   * @param array $clone_fields
   *
   * @param array $type_options
   *
   * @throws \Exception
   */
  protected function createType($label, $id, array $clone_fields, $type_options = []) {
    $this->drupalGet('admin/config/workflow/scheduled-update-type/add');
    // Revision options should not be displayed until entity type that supports it is selected.
    $this->assertNoText('The owner of the last revision.');
    $this->assertNoText('Create New Revisions');
    $edit = $type_options + [
      'label' => $label,
      'id' => $id,
      'update_entity_type' => 'node',
      'update_runner[id]' => 'default_independent',
      'update_runner[after_run]' => UpdateRunnerInterface::AFTER_DELETE,
      'update_runner[invalid_update_behavior]' => UpdateRunnerInterface::INVALID_DELETE,
      'update_runner[update_user]' => UpdateRunnerInterface::USER_UPDATE_RUNNER,
    ];

    $this->drupalPostAjaxForm(NULL, $edit, 'update_entity_type');

    $this->assertText('The owner of the last revision.');
    $this->assertText('Create New Revisions');
    $edit = $type_options + [
      'label' => $label,
      'id' => $id,
      'update_entity_type' => 'node',
      'update_runner[id]' => 'default_independent',
      'update_runner[after_run]' => UpdateRunnerInterface::AFTER_DELETE,
      'update_runner[invalid_update_behavior]' => UpdateRunnerInterface::INVALID_DELETE,
      'update_runner[update_user]' => UpdateRunnerInterface::USER_UPDATE_RUNNER,
      'update_runner[create_revisions]' => UpdateRunnerInterface::REVISIONS_YES,
    ];
    $this->drupalPostForm(NULL,
      $edit,
      t('Save')
    );
    $this->assertUrl("admin/config/workflow/scheduled-update-type/$id/clone-fields");
    $this->assertText("Created the $label Scheduled Update Type.");
    $this->assertText("Select fields to add to these updates");
    $this->checkNodeProperties();
    // No config fields have been added but storage definition is there
    $this->assertNoText('node.body');

    $this->cloneFields($id, $clone_fields);

  }

  protected function checkAccessDenied($path = NULL) {
    if ($path) {
      $this->drupalGet($path);
    }
    $this->assertText('Access denied', 'Accessed denied on path: ' . $path);
  }
}
