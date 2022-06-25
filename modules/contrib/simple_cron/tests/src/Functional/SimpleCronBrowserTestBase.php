<?php

namespace Drupal\Tests\simple_cron\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Provides a test case for functional simple cron tests.
 *
 * @package Drupal\Tests\simple_cron\Functional
 */
abstract class SimpleCronBrowserTestBase extends BrowserTestBase {

  /**
   * Assert table element exists.
   *
   * @param int $row
   *   The table row.
   * @param int $column
   *   The table column.
   * @param string $value
   *   The table html value.
   * @param bool $exists
   *   (optional) Is xpath exists. Default to TRUE.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertTableElementExists(int $row, int $column, string $value, bool $exists = TRUE): void {
    $xpath = $this->assertSession()->buildXPathQuery(
      '//form[@class="simple-cron-list"]/table/tbody/tr[:row]/td[:column and text() = :label]',
      [
        ':row' => $row,
        ':column' => $column,
        ':label' => $value,
      ]
    );

    if ($exists) {
      $this->assertSession()->elementExists('xpath', $xpath);
    }
    else {
      $this->assertSession()->elementNotExists('xpath', $xpath);
    }
  }

  /**
   * Update lock status.
   *
   * @param string $job_id
   *   The job id.
   * @param bool $lock
   *   Is locked.
   *
   * @throws \Exception
   */
  protected function updateLockStatus(string $job_id, bool $lock): void {
    $database = $this->container->get('database');

    if ($lock) {
      $database->insert('semaphore')
        ->fields([
          'name' => 'simple_cron:' . $job_id,
          'value' => uniqid(mt_rand(), TRUE),
          'expire' => time() + 3600,
        ])
        ->execute();
    }
    else {
      $database->delete('semaphore')
        ->condition('name', 'simple_cron:' . $job_id)
        ->execute();
    }
  }

  /**
   * Runs single cron on the test site.
   *
   * @param string $job_id
   *   The job id.
   * @param bool $force
   *   (optional) Is forced. Defaults to FALSE.
   */
  protected function singleCronJobRun(string $job_id, bool $force = FALSE): void {
    $this->drupalGet('cron/' . $this->container->get('state')->get('system.cron_key'), [
      'query' => [
        'job' => $job_id,
        'force' => $force,
      ],
    ]);
  }

}
