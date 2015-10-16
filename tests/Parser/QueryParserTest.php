<?php

namespace Gdbots\Tests\QueryParser\Parser;

use Gdbots\QueryParser\Parser\QueryParser;
use Gdbots\QueryParser\Parser\QueryScanner;

class QueryParserTest extends \PHPUnit_Framework_TestCase
{
    /** QueryParser */
    protected $parser;

    public function setUp()
    {
        $this->parser = new QueryParser();
    }

    public function tearDown()
    {
        $this->parser = null;
    }

    /**
     * @dataProvider getTestParseWithOneClassDataprovider
     */
    public function testParseNode($string, $class, $isList = false)
    {
        $this->parser->readString($string);
        $result = $this->parser->parse();

        $expressions = $result->getExpressions();

        $this->assertInstanceOf($class, $isList ? $result : $expressions[0]);
    }

    public function getTestParseWithOneClassDataprovider()
    {
        return [
            ['phrase', 'Gdbots\QueryParser\Node\Word'],
            ['"phrase"', 'Gdbots\QueryParser\Node\Text'],
            ['country:"United State"', 'Gdbots\QueryParser\Node\ExplicitTerm'],
            ['phrase^boost', 'Gdbots\QueryParser\Node\ExplicitTerm'],
            ['-phrase', 'Gdbots\QueryParser\Node\ExcludeTerm'],
            ['+phrase', 'Gdbots\QueryParser\Node\IncludeTerm'],
            ['#phrase', 'Gdbots\QueryParser\Node\Hashtag'],
            ['@phrase', 'Gdbots\QueryParser\Node\Mention'],
            ['phrase word', 'Gdbots\QueryParser\Node\OrExpressionList', true],
            ['phrase OR word', 'Gdbots\QueryParser\Node\OrExpressionList', true],
            ['phrase AND word', 'Gdbots\QueryParser\Node\AndExpressionList', true],
            ['(phrase)', 'Gdbots\QueryParser\Node\Subexpression']
        ];
    }

    public function testParseTextWithUnclosedQuotes()
    {
        $this->parser->readString('"phrase');
        $result = $this->parser->parse();
        $expressions = $result->getExpressions(QueryScanner::T_WORD);
        $this->assertInstanceOf('Gdbots\QueryParser\Node\Word', $expressions[0]);
    }

    public function testParseInvalidExcludeTermError()
    {
        $this->parser->readString('-"phrase');
        $result = $this->parser->parse();
        $this->assertNull($result);
        $this->assertTrue($this->parser->hasErrors());
    }
}
