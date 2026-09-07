<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every <script> block in every Blade view is valid JavaScript.
 *
 * A syntax error in one does not fail loudly - it kills the whole block, so
 * every function declared in it is undefined and the page's buttons quietly
 * do nothing. Nothing in PHP or Blade catches it, and it has happened here
 * more than once: a string broken across two real lines, which is legal in
 * PHP and a syntax error in JavaScript.
 *
 * Blade expressions are swapped for placeholders first, since node cannot be
 * expected to parse them.
 */
class BladeScriptsParseTest extends TestCase
{
    public function test_no_blade_view_ships_broken_javascript(): void
    {
        exec('node --version 2>&1', $out, $status);

        if ($status !== 0) {
            $this->markTestSkipped('node is not available to parse the script blocks');
        }

        $broken = [];
        $checked = 0;

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);

            if (! preg_match_all('/<script(?:\s[^>]*)?>(.*?)<\/script>/s', $source, $matches)) {
                continue;
            }

            foreach ($matches[1] as $index => $block) {
                // A block that only loads a src, or is a template, has nothing to check.
                if (trim($block) === '') {
                    continue;
                }

                $js = $this->withoutBlade($block);

                // Anything still carrying PHP after the swap is a block this
                // cannot faithfully turn into JavaScript, and a guess would
                // report errors that are the guess's, not the view's.
                if (preg_match('/@\w+|\$\w+\s*(?:->|=>)|::/', $js)) {
                    continue;
                }

                $temp = tempnam(sys_get_temp_dir(), 'blade').'.js';
                file_put_contents($temp, $js);

                exec('node --check '.escapeshellarg($temp).' 2>&1', $lines, $code);

                if ($code !== 0) {
                    $where = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
                    $broken[] = $where.' (block '.$index.'): '.trim(implode(' ', array_slice($lines, 1, 3)));
                }

                unset($lines);
                @unlink($temp);
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked, 'no script blocks were found to check');
        $this->assertSame([], $broken, "Blade views shipping unparsable JavaScript:\n".implode("\n", $broken));
    }

    /** @return list<string> */
    private function bladeFiles(): array
    {
        $files = [];

        $walk = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($walk as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Blade constructs become something node can parse.
     *
     * The order matters: {{ }} and @json(...) stand where a value goes, so
     * they become a literal. @if/@foreach wrap statements, so they go
     * entirely - what is left is still representative of the braces and
     * quotes, which is what this is checking.
     */
    private function withoutBlade(string $js): string
    {
        // A @php block inside a <script> is PHP, not JavaScript - the whole
        // thing goes, contents and all, or node reads its statements as JS.
        $js = preg_replace('/@php.*?@endphp/s', '', $js);
        $js = preg_replace('/\{\{--.*?--\}\}/s', '', $js);
        $js = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '0', $js);
        $js = preg_replace('/@json\s*\((?:[^()]|\([^()]*\))*\)/s', '0', $js);
        $js = preg_replace('/@(?:php|endphp|if|elseif|else|endif|foreach|endforeach|forelse|empty|endforelse|for|endfor|while|endwhile|isset|endisset|auth|endauth|include|includeIf|can|endcan|error|enderror)\b(?:\s*\((?:[^()]|\([^()]*\))*\))?/s', '', $js);

        return $js;
    }
}
