<?php
/**
 * DBSteward unit test scratch directory management
 *
 * @package DBSteward
 * @license http://www.opensource.org/licenses/bsd-license.php Simplified BSD License
 */

use PHPUnit\Framework\Attributes\After;

/**
 * Gives each test its own scratch directory for the files it writes (definition
 * XML, generated SQL, slonik output), outside the source tree.
 *
 * - Isolated: every test gets a fresh, uniquely named directory, so tests cannot
 *   read stale output from a previous run or from another test, and the suite is
 *   safe to run in parallel.
 * - Lazy: the directory is only created when a test asks for a path.
 * - Self-cleaning: removed after a passing test, kept after a failing one so the
 *   output can be inspected (the path is printed to STDERR).
 *
 * The root defaults to <system temp dir>/dbsteward-tests and can be changed with
 * the DBSTEWARD_TEST_OUTPUT_DIR environment variable (CI uploads it on failure).
 */
trait TestWorkspace {

  private ?string $workspace_dir = null;

  /**
   * Absolute path of $relative inside this test's scratch directory, or of the
   * directory itself when $relative is empty. Creates the directory on first use.
   */
  protected function workspacePath(string $relative = ''): string {
    if ($this->workspace_dir === null) {
      $root = getenv('DBSTEWARD_TEST_OUTPUT_DIR') ?: sys_get_temp_dir() . '/dbsteward-tests';
      $label = preg_replace('/[^A-Za-z0-9_.-]+/', '_', static::class . '-' . $this->name() . '-' . $this->dataName());
      $dir = $root . '/' . trim($label, '-_') . '-' . bin2hex(random_bytes(4));
      if (!mkdir($dir, 0777, TRUE) && !is_dir($dir)) {
        throw new RuntimeException("Could not create test workspace $dir");
      }
      $this->workspace_dir = $dir;
    }
    return $relative === '' ? $this->workspace_dir : $this->workspace_dir . '/' . $relative;
  }

  /**
   * Absolute path of a subdirectory of this test's scratch directory, created if needed.
   */
  protected function workspaceSubdir(string $name): string {
    $dir = $this->workspacePath($name);
    if (!is_dir($dir) && !mkdir($dir, 0777, TRUE) && !is_dir($dir)) {
      throw new RuntimeException("Could not create test workspace directory $dir");
    }
    return $dir;
  }

  #[After]
  protected function removeWorkspace(): void {
    if ($this->workspace_dir === null) {
      return;
    }
    $dir = $this->workspace_dir;
    $this->workspace_dir = null;

    $status = $this->status();
    if ($status->isFailure() || $status->isError()) {
      fwrite(STDERR, "\nTest output kept for inspection: $dir\n");
      return;
    }

    $entries = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
      $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($dir);
  }
}
