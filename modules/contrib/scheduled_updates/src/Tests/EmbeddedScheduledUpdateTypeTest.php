<?php
/**
 * @file
 * Contains \Drupal\scheduled_updates\Tests\EmbeddedScheduledUpdateTypeTest.
 */


namespace Drupal\scheduled_updates\Tests;


/**
 * Class IndependentScheduledUpdateTypeTest
 *
 * @group scheduled_updates
 */
class EmbeddedScheduledUpdateTypeTest extends ScheduledUpdatesTestBase {

  protected function setUp() {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
  }

  public function testCreateType() {
    $type_id = 'foo';
    $label = 'Foo Type';
    $clone_fields = [
      'base_fields[title]' => [
        'input_value' => 'title',
        'label' => t('Title'),
      ]
    ];
    $options['update_runner[id]'] = 'default_embedded';
    $this->createType($label, $type_id, $clone_fields, $options);
  }

  protected function createType($label, $type_id, array $clone_fields, $type_options = []) {
    parent::createType($label, $type_id, $clone_fields, $type_options);
    $this->confirmNoAddForm($label, $type_id);
    // @todo - test, inline entity form on target, adding updates, running updates.

  }

  /**
   * Make sure Referenced types do not have a direct add form.
   *
   * @param $label
   * @param $type_id
   *
   * @internal param $id
   */
  protected function confirmNoAddForm($label, $type_id) {
    $logged_id_user = $this->loggedInUser;
    $add_user = $this->createUser(
      ["create $type_id scheduled updates"]
    );
    $this->drupalLogin($add_user);

    $this->drupalGet('admin/content/scheduled-update/add');
    $this->assertNoText($label, 'Refereneced type label does not appear on update add page.');
    $this->checkAccessDenied('admin/content/scheduled-update/add/' . $type_id);

    $this->drupalLogin($logged_id_user);

  }


}
