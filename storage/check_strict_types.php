<?php
/**
 * ==========================================================================
 * check_strict_types.php
 * ==========================================================================
 */
declare(strict_types=1);
$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../public'));
foreach ($dir as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $path = $file->getPathname();
    $content = file_get_contents($path);
    $tokens = token_get_all($content);
    
    $hasDeclare = false;
    $hasExecutableBefore = false;
    $precedingTokens = [];
    
    foreach ($tokens as $token) {
        if (is_array($token)) {
            $id = $token[0];
            $text = $token[1];
            if ($id === T_DECLARE) {
                $hasDeclare = true;
                break;
            }
            if ($id !== T_WHITESPACE && $id !== T_OPEN_TAG && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                $hasExecutableBefore = true;
                $precedingTokens[] = $text;
            }
        } else {
            $hasExecutableBefore = true;
            $precedingTokens[] = $token;
        }
    }
    
    if ($hasDeclare && $hasExecutableBefore) {
        echo "FAIL: " . $path . "\n";
        echo "  Preceding tokens: " . implode(', ', array_slice($precedingTokens, 0, 5)) . "\n\n";
    }
}
