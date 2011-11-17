<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;
use GitElephant\GitBinary,
    GitElephant\Command\Caller,
    GitElephant\Repository,
    GitElephant\Objects\Tree,
    GitElephant\Objects\TreeBranch;


require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';

/**
 * Features context.
 */
class FeatureContext extends BehatContext
{
    private $path;
    /**
     * @var GitElephant\Repository
     */
    private $repository;
    private $caller;
    private $tree;
    private $callResult;

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param   array   $parameters     context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        spl_autoload_register(function($class)
        {
            $file = __DIR__.'/../../src/'.strtr($class, '\\', '/').'.php';
            if (file_exists($file)) {
                require $file;
                return true;
            }
        });
    }

    /**
     * @Given /^I am in a folder$/
     */
    public function iAmInAFolder()
    {
        $tempDir = realpath(sys_get_temp_dir()).'gitelephant_'.md5(uniqid(rand(),1));
        $tempName = tempnam($tempDir, 'gitelephant');
        $this->path = $tempName;
        unlink($this->path);
        mkdir($this->path);
        $binary = new GitBinary('/usr/local/bin/git');
        $this->caller = new Caller($binary, $this->path);
        $this->repository = new Repository($this->path);
    }

    /**
     * @Given /^I init the repository$/
     */
    public function iInitTheRepository()
    {
        $this->repository->init();
    }


    /**
     * @Given /^I add a file named "([^"]*)"$/
     */
    public function iAddAFileNamed($name)
    {
        $filename = $this->path.DIRECTORY_SEPARATOR.$name;
        $handle = fopen($filename, 'w');
        fwrite($handle, 'test content');
        fclose($handle);
    }

    /**
     * @Given /^I add a folder named "([^"]*)"$/
     */
    public function iAddAFolderNamed($name)
    {
        mkdir($this->path.DIRECTORY_SEPARATOR.$name);
    }

    /**
     * @When /^I add to the repository "([^"]*)"$/
     */
    public function iAddToTheRepository($what)
    {
        $this->repository->stage($what);
    }


    /**
     * @Given /^I add a file in folder "([^"]*)" "([^"]*)"$/
     */
    public function iAddAFileInFolder($name, $folder)
    {
        $filename = $this->path.DIRECTORY_SEPARATOR.$folder.DIRECTORY_SEPARATOR.$name;
        $handle = fopen($filename, 'w');
        fwrite($handle, 'test content');
        fclose($handle);
    }

    /**
     * @Given /^I commit and stage with message "([^"]*)"$/
     */
    public function iCommitAndStageWithMessage($message)
    {
        $this->repository->commit($message, true);
    }

    /**
     * @Given /^I commit with message "([^"]*)"$/
     */
    public function iCommitWithMessage($message)
    {
        $this->repository->commit($message, false);
    }

    /**
     * @Given /^I start a test repository$/
     */
    public function iStartATestRepository()
    {
        $this->iAmInAFolder('test');
        $this->iInitTheRepository();
        $this->iAddAFileNamed('test-file');
        $this->iCommitAndStageWithMessage('test-message');
    }



    /**
     * @When /^I get tree "([^"]*)"$/
     */
    public function iGetTree($ref)
    {
        $this->tree = $this->repository->getTree($ref);
    }

    /**
     * @Then /^I should get a tree object$/
     */
    public function iShouldGetATreeObject()
    {
        $reflectionClass = new ReflectionClass($this->tree);
        assertEquals('GitElephant\Objects\Tree', $reflectionClass->getName(), "The object is not a Tree object but ".$reflectionClass->getName());
        assertContains('ArrayAccess', $reflectionClass->getInterfaceNames(), "The object do not have the ArrayAccess interface");
        assertContains('Countable', $reflectionClass->getInterfaceNames(), "The object do not have the Countable interface");
        assertContains('Iterator', $reflectionClass->getInterfaceNames(), "The object do not have the Iterator interface");
    }

    /**
     * @Then /^I should get the status$/
     */
    public function iShouldGetTheStatus(PyStringNode $string)
    {
        assertEquals($string->getLines(), $this->repository->getStatus(), 'Status should be an array');
    }

    /**
     * @Then /^The status should contains "([^"]*)"$/
     */
    public function theStatusShouldContains($what)
    {
        assertRegExp(sprintf('/(.*)%s(.*)/', preg_quote($what, '/')), implode('',$this->repository->getStatus()), 'the status do not contains '.$what);
    }

    /**
     * @When /^I create a branch from "([^"]*)" "([^"]*)"$/
     */
    public function iCreateABranchFrom($name, $from)
    {
        $this->repository->createBranch($name, $from);
    }

    /**
     * @Given /^The repository has the method "([^"]*)"$/
     */
    public function theRepositoryHasTheMethod($methodName)
    {
        $reflectionClass = new ReflectionClass($this->repository);
        $methods = $reflectionClass->getMethods();
        $methodsName = array_map(function(ReflectionMethod $method) { return $method->getName(); }, $methods);
        if (!in_array($methodName, $methodsName)) {
            throw new Exception(sprintf("the method %s do not exists on the %s class", $methodName, $reflectionClass->getName()));
        }
    }

    /**
     * @Given /^I should get an array of objects "([^"]*)"$/
     */
    public function iShouldGetAnArrayOfObjects($objectName)
    {
        if (!is_array($this->callResult)) {
            throw new Exception("The result is not an array");
        }
    }

    /**
     * @When /^I delete the branch "([^"]*)"$/
     */
    public function iDeleteTheBranch($name)
    {
        $this->repository->deleteBranch($name);
    }

    /**
     * @When /^I create a tag "([^"]*)"$/
     */
    public function iCreateATag($name)
    {
        $this->repository->createTag($name);
    }

    /**
     * @Then /^Method should get an array of "([^"]*)" "([^"]*)"$/
     */
    public function methodShouldGetAnArrayOf($methodName, $objectsName)
    {
        $result = call_user_func(array($this->repository, $methodName));
        foreach ($result as $single) {
            $reflectionClass = new ReflectionClass($single);
            if ($reflectionClass->getName() !== $objectsName) {
                throw new Exception(sprintf("not all objects in the array are %s, at least one is %s", $objectsName, $reflectionClass->getName()));
            }
        }
    }

    /**
     * @Then /^Method should get a count of "([^"]*)" (\d+)$/
     */
    public function methodShouldGetACountOf($methodName, $count)
    {
        $result = call_user_func(array($this->repository, $methodName));
        if (!is_array($result)) {
            assertInstanceOf('Countable', $result, 'The result is not a Countable object');
        }
        assertEquals($count, count($result), sprintf('The result is not %s but %s', $count, count($result)));
    }

    /**
     * @Given /^Tree should get a count of (\d+)$/
     */
    public function treeShouldGetACountOf($count)
    {
        assertEquals($count, count($this->tree), sprintf('Tree count is not %s but %s', $count, count($this->tree)));
    }

    /**
     * @When /^I delete a tag "([^"]*)"$/
     */
    public function iDeleteATag($name)
    {
        $this->repository->deleteTag($name);
    }


}
