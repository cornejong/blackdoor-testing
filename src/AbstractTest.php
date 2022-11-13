<?php

namespace Blackdoor\Testing;

use ReflectionMethod;
use ReflectionClass;
use Blackdoor\Util\Str;

abstract class AbstractTest
{
    public $failDescriptor = '';
    protected $evaluations = [];
    protected $tests = [];

    public $timePrecision = 5;

    public function __construct()
    {
        /* Get my own reflection */
        $reflection = new ReflectionClass($this);
        /* Loop over all the public methods */
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            /* If it starts with 'test' or has a test declaration in it's docComment */
            if (Str::startsWith('test', $method->getName()) || preg_match('/\@test/', $method->getDocComment())) {
                /* Add it to the tests array */
                $this->tests[] = $method->getName();
            }
        }
    }

    public function mustPass()
    {
        return property_exists($this, "mustPass") ? boolval($this->mustPass) : true;
    }

    /**
     * Runs all the tests in the test class
     *
     * @param callable $startCallback       Called on the start of every test and provides the test name
     * @param callable $resultCallback      Called after every test, provides the test results (array)
     * @return void
     */
    public function run(callable $startCallback, callable $resultCallback)
    {
        /* If we have a boot method */
        if (\method_exists($this, 'init')) {
            $startCallback("Initializer");
            $startTime = \hrtime(true);
            call_user_func([$this, 'init']);
            call_user_func($resultCallback, [
                'status' => true,
                'name' => "init",
                'formattedName' => "Initializer",
                'duration' => (number_format(((hrtime(true) - $startTime) / 1e+9), $this->timePrecision))
            ], []);
        }

        foreach ($this->tests as $test) {
            $startCallback($this->formatTestName($test));
            $this->assertionStart = \hrtime(true);
            $testStart = \hrtime(true);
            call_user_func([$this, $test]);
            call_user_func($resultCallback, [
                'name' => $test,
                'formattedName' =>  $this->formatTestName($test),
                'duration' => (number_format(((hrtime(true) - $testStart) / 1e+9), $this->timePrecision))
            ], $this->getEvaluation($test) ?? []);

            if (in_array(false, array_column($this->evaluations[$test] ?? [], "status"))) {
                break;
            }
        }

        /* If we have a boot method */
        if (\method_exists($this, 'shutdown')) {
            $startCallback("Shutdown");
            $startTime = \hrtime(true);
            call_user_func([$this, 'shutdown']);
            call_user_func($resultCallback, [
                'status' => true,
                'name' => "shutdown",
                'formattedName' => "Shutdown",
                'duration' => (number_format(((hrtime(true) - $startTime) / 1e+9), $this->timePrecision))
            ], []);
        }
    }

    /**
     * Returns the number of tests present in the test class
     *
     * @return int
     */
    public function numberOfTests(): int
    {
        return count($this->tests);
    }

    /**
     * returns ether a single or all test evaluations
     *
     * @param string $test      The name of the test
     * @return array|null
     */
    public function getEvaluation(string $test = null)
    {
        return is_null($test) ? $this->evaluations : $this->evaluations[$test] ?? null;
    }

    protected function formatTestName(string $name)
    {
        if (Str::startsWith("test", $name)) {
            $name = lcfirst(\substr($name, 4));
        }

        return ucwords(\str_replace('_', ' ', Str::tableize($name)));
    }

    public function groupName()
    {
        $elements = explode('\\', static::class);
        return array_pop($elements);
    }

    /**
     * Evaluates the result and adds it to the array
     *
     * @param mixed $result
     * @param mixed $expected
     * @return void
     */
    public function evaluate($result, $expected = true, string $name = null, string $testName = null)
    {
        /* Get the name of the test that called the method */
        $testName = $testName ?? debug_backtrace()[1]['function'];
        /* If it's not a known test... just ignore for now */
        if (!in_array($testName, $this->tests)) {
            return;
        }

        /* Get the result */
        $status = $result === $expected;

        /* format the response */
        $this->evaluations[$testName][] = [
            'status' => $status,
            'name' => $name ?? ("Assertion " . $this->nextAssertionNumber($testName)),
            'result' => $result,
            'expected' => $expected,
            /* duration in seconds */
            'duration' => (number_format(((hrtime(true) - $this->assertionStart) / 1e+9), $this->timePrecision))
        ];

        $this->assertionStart = hrtime(true);
    }

    public function assert($result, $expected = true, string $name = null, $testName = null)
    {
        /* Get the name of the test that called the method */
        $testName = $testName ?? debug_backtrace()[1]['function'];
        /* If it's not a known test... just ignore for now */
        if (!in_array($testName, $this->tests)) {
            return;
        }

        return $this->evaluate($result, $expected, $name, $testName);
    }

    public function nextAssertionNumber(string $testName): int
    {
        return count($this->evaluations[$testName] ?? []) + 1;
    }

    public static function stringStartsWith(string $needle, string $string)
    {
        return $needle === substr($string, 0, strlen($needle)) ? true : false;
    }
}
