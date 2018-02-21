<?php

namespace Drupal\Tests\social_auth_bitbucket\Functional;

use Drupal\social_api\SocialApiSettingsFormBaseTest;

/**
 * Test Social Auth Bitbucket settings form.
 *
 * @group social_auth
 *
 * @ingroup social_auth_bitbucket
 */
class SocialAuthBitbucketSettingsFormTest extends SocialApiSettingsFormBaseTest {
  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['social_auth_bitbucket'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->module = 'social_auth_bitbucket';
    $this->socialNetwork = 'bitbucket';
    $this->moduleType = 'social-auth';

    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function testIsAvailableInIntegrationList() {
    $this->fields = ['key', 'secret'];

    parent::testIsAvailableInIntegrationList();
  }

  /**
   * {@inheritdoc}
   */
  public function testSettingsFormSubmission() {
    $this->edit = [
      'key' => $this->randomString(10),
      'secret' => $this->randomString(10),
    ];

    parent::testSettingsFormSubmission();
  }

}
