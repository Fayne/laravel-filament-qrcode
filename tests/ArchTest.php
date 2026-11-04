<?php

it('does not use debugging functions in package source', function () {
    $sourcePath = dirname(__DIR__) . '/src';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourcePath));
    $violations = [];

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $tokens = token_get_all(file_get_contents($file->getPathname()));
        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_STRING || ! in_array(strtolower($token[1]), ['dd', 'dump', 'ray'], true)) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;
            $next = $tokens[$index + 1] ?? null;
            if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                continue;
            }

            if ($next === '(') {
                $violations[] = $file->getPathname() . ':' . $token[2];
            }
        }
    }

    expect($violations)->toBeEmpty();
});
