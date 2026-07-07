<?php

namespace Tests\Unit;

use App\Support\PermissionCatalog;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ReferencedPermissionSlugTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const SCAN_DIRECTORIES = [
        'app',
        'resources/js/vue-app',
        'routes',
    ];

    /**
     * @var list<string>
     */
    private const IGNORE_PATH_FRAGMENTS = [
        'app/Support/PermissionDefinitions.php',
        'vendor/',
        'node_modules/',
    ];

    public function test_referenced_permission_slugs_exist_in_catalog(): void
    {
        $catalogSlugs = PermissionCatalog::slugs();
        $referenced = $this->collectReferencedSlugs();
        $missing = array_values(array_diff($referenced, $catalogSlugs));

        sort($missing);

        $this->assertSame(
            [],
            $missing,
            'Referenced permission slugs missing from PermissionDefinitions: '.implode(', ', $missing),
        );
    }

    /**
     * @return list<string>
     */
    private function collectReferencedSlugs(): array
    {
        $pattern = '/(?:hasPermission|hasAnyPermission|canInCompany|permission)\s*\(\s*[\'"]([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)[\'"]|permission:\s*[\'"]([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)[\'"]|meta:\s*\{[^}]*permission:\s*[\'"]([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)[\'"]/';
        $slugs = [];

        foreach (self::SCAN_DIRECTORIES as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $relativePath = str_replace(base_path().'/', '', $file->getPathname());

                if ($this->shouldIgnorePath($relativePath)) {
                    continue;
                }

                $extension = $file->getExtension();

                if (! in_array($extension, ['php', 'js', 'vue'], true)) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                if (preg_match_all($pattern, $contents, $matches)) {
                    foreach (array_merge($matches[1], $matches[2], $matches[3]) as $slug) {
                        if ($slug !== '') {
                            $slugs[$slug] = true;
                        }
                    }
                }

                if ($extension === 'js' || $extension === 'vue') {
                    if (preg_match_all("/permission:\s*'([a-z][a-z0-9_]*(?:\\.[a-z][a-z0-9_]*)+)'/", $contents, $jsMatches)) {
                        foreach ($jsMatches[1] as $slug) {
                            $slugs[$slug] = true;
                        }
                    }
                }
            }
        }

        return array_keys($slugs);
    }

    private function shouldIgnorePath(string $relativePath): bool
    {
        foreach (self::IGNORE_PATH_FRAGMENTS as $fragment) {
            if (str_contains($relativePath, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
