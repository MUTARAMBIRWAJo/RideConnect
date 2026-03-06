<?php

declare(strict_types=1);

/**
 * Fails when a Filament navigation group has an icon while one or more
 * resources/pages in that same group also define navigation icons.
 */

$root = dirname(__DIR__);

$panelProviderFiles = glob($root . '/app/Providers/Filament/*PanelProvider.php') ?: [];
$filamentFiles = iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app/Filament')));

$groupIconsByProvider = [];

foreach ($panelProviderFiles as $providerFile) {
    $content = @file_get_contents($providerFile);

    if ($content === false) {
        fwrite(STDERR, "Unable to read file: {$providerFile}" . PHP_EOL);
        exit(2);
    }

    preg_match_all('/NavigationGroup::make\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)(.*?)(?=NavigationGroup::make\(|\]\)|\)\s*->|\n\s*\)\s*;)/s', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $groupName = trim($match[1]);
        $chain = $match[2] ?? '';

        if ($groupName !== '' && preg_match('/->icon\s*\(/', $chain) === 1) {
            $groupIconsByProvider[$providerFile][$groupName] = true;
        }
    }
}

$groupedItemsWithIcons = [];

foreach ($filamentFiles as $fileInfo) {
    if (! $fileInfo instanceof SplFileInfo || ! $fileInfo->isFile()) {
        continue;
    }

    if ($fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $content = @file_get_contents($path);

    if ($content === false) {
        fwrite(STDERR, "Unable to read file: {$path}" . PHP_EOL);
        exit(2);
    }

    $group = null;
    $hasIcon = false;

    if (preg_match('/\$navigationGroup\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/', $content, $match) === 1) {
        $group = trim($match[1]);
    } elseif (preg_match('/function\s+getNavigationGroup\s*\([^)]*\)\s*:[^{]+\{[\s\S]*?return\s+[\'\"]([^\'\"]+)[\'\"]\s*;[\s\S]*?\}/', $content, $match) === 1) {
        $group = trim($match[1]);
    }

    if (preg_match('/\$navigationIcon\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/', $content) === 1) {
        $hasIcon = true;
    } elseif (preg_match('/function\s+getNavigationIcon\s*\([^)]*\)\s*:[^{]+\{[\s\S]*?return\s+[\'\"]([^\'\"]+)[\'\"]\s*;[\s\S]*?\}/', $content) === 1) {
        $hasIcon = true;
    }

    if ($group !== null && $group !== '' && $hasIcon) {
        $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
        $groupedItemsWithIcons[$group][] = $relative;
    }
}

$violations = [];

foreach ($groupIconsByProvider as $providerFile => $groups) {
    $providerRelative = ltrim(str_replace($root, '', $providerFile), DIRECTORY_SEPARATOR);

    foreach (array_keys($groups) as $groupName) {
        $items = $groupedItemsWithIcons[$groupName] ?? [];

        if ($items !== []) {
            $violations[] = [
                'provider' => $providerRelative,
                'group' => $groupName,
                'items' => $items,
            ];
        }
    }
}

if ($violations === []) {
    echo "PASS: No Filament navigation icon conflicts detected." . PHP_EOL;
    exit(0);
}

echo "FAIL: Filament navigation icon conflict(s) detected." . PHP_EOL;

echo PHP_EOL;
foreach ($violations as $violation) {
    echo "Panel Provider: {$violation['provider']}" . PHP_EOL;
    echo "Group: {$violation['group']} (group has icon)" . PHP_EOL;
    echo "Items with icons in same group:" . PHP_EOL;

    foreach ($violation['items'] as $item) {
        echo "  - {$item}" . PHP_EOL;
    }

    echo PHP_EOL;
}

echo "Fix by removing icons from either the group or its items." . PHP_EOL;

exit(1);
