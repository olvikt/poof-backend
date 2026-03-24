#!/usr/bin/env php
<?php

declare(strict_types=1);

const WORKFLOW_FILE = '.github/workflows/tests.yml';
const DOCS_FILE = 'docs/release-gates.md';
const WORKFLOW_STEP_NAME = 'Run minimal blocking release suite';
const DOCS_SECTION_MARKER = 'Current blocking CI gate:';

function fail(string $message): void
{
    fwrite(STDERR, "[release-gates-drift] {$message}\n");
    exit(1);
}

function readFileOrFail(string $path): string
{
    if (!is_file($path)) {
        fail("File not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        fail("Unable to read file: {$path}");
    }

    return $content;
}

function normalizeTestPath(string $path): string
{
    return trim($path, " \t\n\r\0\x0B`';,");
}

function extractWorkflowSuite(string $workflowContent): array
{
    $lines = preg_split('/\R/', $workflowContent);
    if ($lines === false) {
        fail('Unable to split workflow file into lines.');
    }

    $stepIndex = null;
    $stepIndent = null;

    foreach ($lines as $i => $line) {
        if (preg_match('/^(\s*)-\s+name:\s*Run minimal blocking release suite\s*$/', $line, $matches) === 1) {
            $stepIndex = $i;
            $stepIndent = strlen($matches[1]);
            break;
        }
    }

    if ($stepIndex === null || $stepIndent === null) {
        fail('Unable to find workflow step "' . WORKFLOW_STEP_NAME . '" in ' . WORKFLOW_FILE . '.');
    }

    $runIndex = null;
    $runIndent = null;

    for ($i = $stepIndex + 1, $count = count($lines); $i < $count; $i++) {
        $line = $lines[$i];

        if (trim($line) === '') {
            continue;
        }

        $currentIndent = strlen($line) - strlen(ltrim($line, ' '));
        if ($currentIndent <= $stepIndent && preg_match('/^\s*-\s+name:/', $line) === 1) {
            break;
        }

        if (preg_match('/^(\s*)run:\s*\|\s*$/', $line, $matches) === 1) {
            $runIndex = $i;
            $runIndent = strlen($matches[1]);
            break;
        }
    }

    if ($runIndex === null || $runIndent === null) {
        fail('Unable to find multiline run block for step "' . WORKFLOW_STEP_NAME . '" in ' . WORKFLOW_FILE . '.');
    }

    $tests = [];
    for ($i = $runIndex + 1, $count = count($lines); $i < $count; $i++) {
        $line = $lines[$i];

        if (trim($line) === '') {
            continue;
        }

        $currentIndent = strlen($line) - strlen(ltrim($line, ' '));
        if ($currentIndent <= $runIndent) {
            break;
        }

        if (preg_match_all('/\btests\/[A-Za-z0-9_\/.-]+\.php\b/', $line, $matches) === 1) {
            foreach ($matches[0] as $path) {
                $tests[] = normalizeTestPath($path);
            }
        }
    }

    if ($tests === []) {
        fail('No test file paths were found in workflow step "' . WORKFLOW_STEP_NAME . '".');
    }

    return $tests;
}

function extractDocsSuite(string $docsContent): array
{
    $lines = preg_split('/\R/', $docsContent);
    if ($lines === false) {
        fail('Unable to split docs file into lines.');
    }

    $markerIndex = null;
    foreach ($lines as $i => $line) {
        if (strpos($line, DOCS_SECTION_MARKER) !== false) {
            $markerIndex = $i;
            break;
        }
    }

    if ($markerIndex === null) {
        fail('Unable to find section marker "' . DOCS_SECTION_MARKER . '" in ' . DOCS_FILE . '.');
    }

    $tests = [];
    $started = false;

    for ($i = $markerIndex + 1, $count = count($lines); $i < $count; $i++) {
        $line = $lines[$i];

        if (trim($line) === '') {
            if ($started) {
                continue;
            }
            continue;
        }

        if (
            preg_match('/^\s*-\s+/', $line) === 1
            && preg_match('/\btests\/[A-Za-z0-9_\/.-]+\.php\b/', $line, $matches) === 1
        ) {
            $tests[] = normalizeTestPath($matches[0]);
            $started = true;
            continue;
        }

        if ($started) {
            break;
        }
    }

    if ($tests === []) {
        fail('No documented test entries were found after "' . DOCS_SECTION_MARKER . '" in ' . DOCS_FILE . '.');
    }

    return $tests;
}

function findDuplicates(array $items): array
{
    $counts = array_count_values($items);
    $duplicates = [];

    foreach ($counts as $item => $count) {
        if ($count > 1) {
            $duplicates[] = $item;
        }
    }

    sort($duplicates);
    return $duplicates;
}

function formatList(array $items): string
{
    $formatted = '';
    foreach ($items as $item) {
        $formatted .= "  - {$item}\n";
    }

    return $formatted;
}

$workflowSuite = extractWorkflowSuite(readFileOrFail(WORKFLOW_FILE));
$docsSuite = extractDocsSuite(readFileOrFail(DOCS_FILE));

$workflowDuplicates = findDuplicates($workflowSuite);
$docsDuplicates = findDuplicates($docsSuite);

if ($workflowDuplicates !== []) {
    fail("Duplicate test entries in workflow suite:\n" . formatList($workflowDuplicates));
}

if ($docsDuplicates !== []) {
    fail("Duplicate test entries in docs suite:\n" . formatList($docsDuplicates));
}

$workflowSet = array_values(array_unique($workflowSuite));
$docsSet = array_values(array_unique($docsSuite));

sort($workflowSet);
sort($docsSet);

$missingInDocs = array_values(array_diff($workflowSet, $docsSet));
$missingInWorkflow = array_values(array_diff($docsSet, $workflowSet));

if ($missingInDocs !== [] || $missingInWorkflow !== []) {
    $message = "Drift detected between workflow and documented blocking suite.\n";

    if ($missingInDocs !== []) {
        sort($missingInDocs);
        $message .= "\nPresent in workflow, missing in docs:\n" . formatList($missingInDocs);
    }

    if ($missingInWorkflow !== []) {
        sort($missingInWorkflow);
        $message .= "\nPresent in docs, missing in workflow:\n" . formatList($missingInWorkflow);
    }

    fail(rtrim($message));
}

exit(0);
