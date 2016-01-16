<?php
/**
 * @file
 * Contains \Drupal\scheduled_updates\Tests\IndependentScheduledUpdateTypeTest.
 */


namespace Drupal\scheduled_updates\Tests;


use Drupal\Component\Render\FormattableMarkup;
use Drupal\scheduled_updates\Entity\ScheduledUpdateType;

/**
 * Class IndependentScheduledUpdateTypeTest
 *
 * @group scheduled_updates
 */
class IndependentScheduledUpdateTypeTest extends ScheduledUpdatesTestBase {


  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
  }


  public function testCreateMultiTypes() {
    $label = 'New foo type';
    $id = 'foo';
    $clone_fields = [
      'base_fields[title]' => [
        'input_value' => 'title',
        'label' => t('Title'),
      ]
    ];
    $this->createType($label, $id, $clone_fields);
    $this->checkAddForm($id, $label, $clone_fields, TRUE);

    $label = 'New bar type';
    $id = 'bar';
    $clone_fields = [
      'base_fields[status]' => [
        'input_value' => 'status',
        'label' => t('Publishing status'),
      ]
    ];
    $this->createType($label, $id, $clone_fields);
    $this->checkAddForm($id, $label, $clone_fields, FALSE);
  }

  protected function createType($label, $id, array $clone_fields, $type_options = []) {
    parent::createType($label, $id, $clone_fields, $type_options);
    $this->assertText('Entities to Update', 'Entities to Update field on Independent Update Type');
  }


  protected function checkAddForm($type_id, $label, $fields, $only_type) {
    $logged_id_user = $this->loggedInUser;
    $add_user = $this->createUser(
      ["create $type_id scheduled updates"]
    );
    $this->drupalLogin($add_user);

    $this->drupalGet('admin/content/scheduled-update/add');
    if ($only_type) {
      // Form shown if only type.
      $this->checkFieldLabels($fields);
    }
    else {
      $this->assertText($label);
      /** @var ScheduledUpdateType[] $types */
      $types = ScheduledUpdateType::loadMultiple();
      // Check that all types are shown on the add page.
      foreach ($types as $type) {
        $this->assertText($type->label());
      }

    }
    //$this->assertText($label);
    $this->drupalGet("admin/content/scheduled-update/add/$type_id");
    $this->assertText(new FormattableMarkup('Create @label Scheduled Update', ['@label' => $label]));
    $this->checkFieldLabels($fields);
    $this->drupalLogin($logged_id_user);
  }


}
