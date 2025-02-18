<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Di;

use Hyperf\Di\LazyLoader\PublicMethodVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflection\Adapter\ReflectionMethod;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;

/**
 * @internal
 * @coversNothing
 */
class PublicMethodVisitorTest extends TestCase
{
    public function testVisitInterface()
    {
        $code = <<<'CODETEMPLATE'
<?php
namespace foo;

use bar\ConfigInterface;
interface foo {
	public function hope(bool $a): int;
	public function it(ConfigInterface $a): void;
	public function works(bool $a, float $b = 1);
	public function fluent(): self;
}
CODETEMPLATE;
        $expected = <<<'CODETEMPLATE'
<?php

public function hope(bool $a) : int
{
    return $this->__call(__FUNCTION__, func_get_args());
}
public function it(\bar\ConfigInterface $a) : void
{
    $this->__call(__FUNCTION__, func_get_args());
}
public function works(bool $a, float $b = 1)
{
    return $this->__call(__FUNCTION__, func_get_args());
}
public function fluent() : \foo\foo
{
    return $this->__call(__FUNCTION__, func_get_args());
}
CODETEMPLATE;
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $visitor = new PublicMethodVisitor(...$this->getStmt($code));
        $traverser->addVisitor($visitor);
        $ast = $traverser->traverse($ast);
        $prettyPrinter = new Standard();
        $newCode = $prettyPrinter->prettyPrintFile($visitor->nodes);
        $this->assertEquals($expected, $newCode);
    }

    public function testVisitClass()
    {
        $code = <<<'CODETEMPLATE'
<?php
namespace foo;

use bar\ConfigInterface;
class foo {
	abstract public function hope(bool $a): int;

	public function it(ConfigInterface $a): void{
		sleep(1);
	}
	public function works(bool $a, float $b = 1): int{
		return self::works(false);
	}
	public function fluent(): self {
	    return $this;
	}
}
CODETEMPLATE;
        $expected = <<<'CODETEMPLATE'
<?php

public function hope(bool $a) : int
{
    return $this->__call(__FUNCTION__, func_get_args());
}
public function it(\bar\ConfigInterface $a) : void
{
    $this->__call(__FUNCTION__, func_get_args());
}
public function works(bool $a, float $b = 1) : int
{
    return $this->__call(__FUNCTION__, func_get_args());
}
public function fluent() : \foo\foo
{
    return $this->__call(__FUNCTION__, func_get_args());
}
CODETEMPLATE;
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $visitor = new PublicMethodVisitor(...$this->getStmt($code));
        $traverser->addVisitor($visitor);
        $ast = $traverser->traverse($ast);
        $prettyPrinter = new Standard();
        $newCode = $prettyPrinter->prettyPrintFile($visitor->nodes);
        $this->assertEquals($expected, $newCode);
        $this->assertEquals(4, count($visitor->nodes));
    }

    private function getStmt($code)
    {
        $astLocator = (new BetterReflection())->astLocator();
        $reflector = new ClassReflector(new StringSourceLocator($code, $astLocator));
        $reflectionClass = $reflector->reflect('foo\\foo');
        $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
        $stmts = [];
        foreach ($reflectionMethods as $method) {
            $stmts[] = $method->getAst();
        }
        return [$stmts, 'foo\\foo'];
    }
}
