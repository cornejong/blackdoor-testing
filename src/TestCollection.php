<?php

namespace Blackdoor\Testing;

use Brick\VarExporter\VarExporter;
use Blackdoor\Testing\AbstractTest;

class TestCollection
{
    public static $tests = [];

    public static function register(AbstractTest $test)
    {
        self::$tests[get_class($test)] = $test;
    }

    /**
     * Run All registered tests
     *
     * @return bool         If the tests failed or succeeded
     */
    public function runInConsole(array $tests = null): bool
    {
        $cols = intval(trim(exec("tput cols")));
        $this->cols = $cols;

        $tests = $tests ?? self::$tests;

        print("\n" . $this->formatCenter($cols, "Blackdoor") . "\n");
        foreach ([
            " _____ _____ _____ _____ _____ _____ _____ ",
            "|_   _|   __|   __|_   _|     |   | |   __|",
            "  | | |   __|__   | | | |-   -| | | |  |  |",
            "  |_| |_____|_____| |_| |_____|_|___|_____|",
        ] as $line) {
            print($this->formatCenter($cols, "\033[94;1m$line\033[0;0m\n"));
        }

        print("\n" . $this->formatCenter($cols, "Running " . count($tests) . " tests") . "\n");
        print("\n" . implode("", array_fill(0, $cols, "-")) . "\n");

        $failed = [];
        $totalDuration = 0;

        $haltedResult = null;
        $shouldHalt = false;
        $haltedTestInfo = null;

        foreach ($tests as $className => $test) {
            $groupDuration = 0;
            $testGroupName = $test->groupName();
            $printedLines = 0;

            print("\n- " . $testGroupName . " - Running " . $test->numberOfTests() . " sub-tests.\n");
            $printedLines++;

            $announcerCallback = function ($name) {
                print("  > Running " . $name . "...\n");
            };

            $resultCallback = function (array $testInfo, array $testResults) use (&$failed, $testGroupName, &$groupDuration, $cols, &$printedLines, &$haltedResult, &$haltedTestInfo, $test) {
                print("\033[1F");
                $numberOfResults = count($testResults);

                if ($numberOfResults === 0) {
                    if ($testInfo["name"] === "init") {
                        print($this->justifySpaceBetween($cols, "  \033[93;1m✓\033[0m " . $testInfo['formattedName'] . " - [" . $testInfo['duration'] . "s]", "\033[103;1m          \033[0;0m") . "\n");
                        $printedLines++;
                    } else {
                        print($this->justifySpaceBetween($cols, "  \033[34;1m?\033[0m " . $testInfo['formattedName'] . " - [" . $testInfo['duration'] . "s]", "\033[44;1m  ??????  \033[0;0m  ") . "\n");
                        print("    \033[1mNo test results found!\033[0m\n");
                        $printedLines++;
                        $printedLines++;
                    }
                    return;
                }

                $testStatus = !in_array(false, array_column($testResults, "status"));

                if ($testStatus) {
                    print($this->justifySpaceBetween($cols, "  \033[32;1m✓\033[0m " . $testInfo['formattedName'] . " - [" . $testInfo['duration'] . "s]", "\033[;42;1m  PASSED  \033[0;0m") . "\n");
                    $printedLines++;
                } else {
                    print($this->justifySpaceBetween($cols, "  \033[31;1m✖\033[0m " . $testInfo["formattedName"] . " - [" . $testInfo['duration'] . "s]", "\033[;41;1m  FAILED  \033[0;0m") . "\n");
                    $printedLines++;
                }

                if ($numberOfResults === 1) {
                    if (!$testStatus) {
                        $failed[$testGroupName][$testInfo['name']][] = $testResults[0];
                        $haltedResult = $testResults[0];
                        $haltedTestInfo = $testInfo;
                    }
                    return;
                }

                foreach ($testResults as $index => $result) {
                    $groupDuration += $result['duration'];

                    if ($result["status"]) {
                        print($this->justifySpaceBetween($cols, "    \033[32;1m✓\033[0m " . $result["name"] . " - [" . $result['duration'] . "s]", "\033[;42;1m  PASSED  \033[0;0m") . "\n");
                        $printedLines++;
                    } else {
                        print($this->justifySpaceBetween($cols, "    \033[31;1m✓\033[0m " . $result["name"] . " - [" . $result['duration'] . "s]", "\033[;41;1m  FAILED  \033[0;0m") . "\n");
                        $printedLines++;

                        $failed[$testGroupName][$testInfo['name']][] = $result;
                        $haltedResult = $result;
                        $haltedTestInfo = $testInfo;

                        if ($test->mustPass()) {
                            break;
                        }
                    }
                }
            };

            $test->run($announcerCallback, $resultCallback);

            if (empty($failed[$testGroupName])) {
                print("\033[" . $printedLines . "F" . $this->justifySpaceBetween($cols, "\033[32;1m✓\033[0m $testGroupName - " . $test->numberOfTests() . " sub-tests - [" . number_format($groupDuration, $test->timePrecision) . "s]", "\033[;42;1m  PASSED  \033[;0m") . "\033[" . $printedLines . "E");
            } else {
                print("\033[" . $printedLines . "F" . $this->justifySpaceBetween($cols, "\033[31;1m✖\033[0m $testGroupName - " . $test->numberOfTests() . " sub-tests - [" . number_format($groupDuration, $test->timePrecision) . "s]", "\033[;41;1m  FAILED  \033[;0m") . "\033[" . $printedLines . "E\n");
                // print($this->justifySpaceBetween($cols, "  \033[31;1m✖\033[0m $testGroupName - [" . number_format($groupDuration, $test->timePrecision) . "s]",  "\033[;41;1m  FAILED  \033[;0m") . "\n");

                if ($test->mustPass()) {
                    break;
                }
            }

            $totalDuration += $groupDuration;
        }

        print("\n" . implode("", array_fill(0, $cols, "-")) . "\n\n");

        if (!empty($failed)) {
            print($this->formatCenter($cols, " 01000110 01000001 01001001 01001100 01000101 01000100\n\n\033[31m"));

            $failedArt = [
                " /$$$$$$$$ /$$$$$$  /$$$$$$ /$$       /$$$$$$$$ /$$$$$$$ ",
                "| $\$_____//$\$__  $$|_  $\$_/| $$      | $\$_____/| $\$__  $$",
                "| $$     | $$  \ $$  | $$  | $$      | $$      | $$  \ $$",
                "| $$$$$  | $$$$$$$$  | $$  | $$      | $$$$$   | $$  | $$",
                "| $\$__/  | $\$__  $$  | $$  | $$      | $\$__/   | $$  | $$",
                "| $$     | $$  | $$  | $$  | $$      | $$      | $$  | $$",
                "| $$     | $$  | $$ /$$$$$$| $$$$$$$$| $$$$$$$$| $$$$$$$/",
                "|__/     |__/  |__/|______/|________/|________/|_______/ ",
            ];

            foreach ($failedArt as $line) {
                print($this->formatCenter($cols, $line) . "\n");
            }

            // dd($haltedResult);

            print("\033[0m\n" . $this->formatCenter($cols, "\033[1m" . $test->groupName() . ": " . $haltedTestInfo["formattedName"] . "\033[0m\n"));
            print($this->formatCenter($cols, "Failed the assertion \033[1m" . $haltedResult["name"]) . "\033[0m\n");
            print($this->formatCenter($cols, $this->testResultsForHumans($haltedResult)) . "\n");

            print("\n" . $this->formatCenter($cols, implode("", array_fill(0, $cols * 0.8, "-"))) . "\n\n");

            // print("Expected:\n" . VarExporter::export($haltedResult["expected"]) . "\n\n");
            // print("Result:\n" . VarExporter::export($haltedResult["result"]) . "\n\n");

            print("\n");

            return false;
        } else {
            print($this->formatCenter($cols, " 01010000 01000001 01010011 01010011 01000101 01000100\n\n\033[32m"));

            $passedArt = [
                " /$$$$$$$   /$$$$$$   /$$$$$$   /$$$$$$  /$$$$$$$$ /$$$$$$$ ",
                "| $\$__  $$ /$\$__  $$ /$\$__  $$ /$\$__  $$| $\$_____/| $\$__  $$",
                "| $$  \ $$| $$  \ $$| $$  \__/| $$  \__/| $$      | $$  \ $$",
                "| $$$$$$$/| $$$$$$$$|  $$$$$$ |  $$$$$$ | $$$$$   | $$  | $$",
                "| $\$____/ | $\$__  $$ \____  $$ \____  $$| $\$__/   | $$  | $$",
                "| $$      | $$  | $$ /$$  \ $$ /$$  \ $$| $$      | $$  | $$",
                "| $$      | $$  | $$|  $$$$$$/|  $$$$$$/| $$$$$$$$| $$$$$$$/",
                "|__/      |__/  |__/ \______/  \______/ |________/|_______/ ",
            ];

            foreach ($passedArt as $line) {
                print($this->formatCenter($cols, $line) . "\n");
            }

            print("\033[0m\n" . $this->formatCenter($cols, "\033[1mAll tests Passed!\033[0m [" . number_format($totalDuration, 5) . "s]\n\n\n"));
            return true;
        }
    }

    public function justifySpaceBetween(int $cols, string $stringA, string $stringB, int $strALength = null, int $strBLength = null): string
    {
        if (is_null($strALength)) {
            $strALength = strlen(preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $stringA));
        }

        if (is_null($strBLength)) {
            $strBLength = strlen(preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $stringB));
        }

        $spaces = round($cols - $strALength - $strBLength, 0, PHP_ROUND_HALF_DOWN);
        return $stringA . implode("", array_fill(0, $spaces, " ")) . $stringB;
    }

    public function formatCenter(int $cols, string $string): string
    {
        $stringLength = strlen(preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $string));
        return implode("", \array_fill(0, intval(($cols - $stringLength) / 2), " ")) . $string;
    }

    public function leftPad(string $string, int $cols): string
    {
        $stringLength = strlen(preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $string));
        return implode("", \array_fill(0, $cols - $stringLength, " ")) . $string;
    }

    public function rightPad(string $string, int $cols): string
    {
        $stringLength = strlen(preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $string));
        return $string . implode("", \array_fill(0, $cols - $stringLength, " "));
    }

    public function testResultsForHumans($test): string
    {
        if (gettype($test["expected"]) !== gettype($test['result'])) {
            return "Expected '" . gettype($test["expected"]) . "' but resulted in '" . gettype($test['result']) . "'";
        }

        if (is_bool($test["expected"])) {
            return "Expected '" . ($test["expected"] ? "true" : "false") . "' but resulted in '" . ($test["result"] ? "true" : "false") . "'";
        }

        if (is_array($test["expected"])) {
            return "Expected array but resulted in array with different content.";
            // $forHumans[] = "Expected:\n" . VarExporter::export($test["expected"]) . "\n\nResult:\n" . VarExporter::export($test["result"]);
            // return $forHumans;
        }

        if (is_object($test["expected"])) {
            return "Expected '" . get_class($test["expected"]) . "' but resulted in '" . get_class($test['result']) . "'";
        }

        if (is_string($test["expected"])) {
            return "Expected '" . $test["expected"] . "' but resulted in '" . $test['result'] . "'";
        }

        if (is_numeric($test["expected"])) {
            return "Expected '" . $test["expected"] . "' but resulted in '" . $test['result'] . "'";
        }

        return "Failed the assertion";
    }
}
