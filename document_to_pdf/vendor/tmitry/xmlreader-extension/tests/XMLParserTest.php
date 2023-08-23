<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use tmitry\XMLReaderExtension\XMLParser;

class XMLParserTest extends TestCase
{
	private $fileName;

	public function setUp(): void
	{
		$this->fileName = __DIR__ . '/fixtures/example.xml';
	}

	public function testInit()
	{
		$parser = new XMLParser();
		$this->assertTrue($parser->open($this->fileName));

		$xmlReader = new XMLReader();
		$xmlReader->open($this->fileName);
		$parser = new XMLParser($xmlReader);
		$this->assertInstanceOf(XMLParser::class, $parser);

		$this->assertObjectHasAttribute('xmlReader', $parser);
	}

	public function testParse()
	{
		$parser = new XMLParser();
		$parser->open($this->fileName);

		$products = [];
		$expectedProducts = [
			'Apple iPhone 11 64GB',
			'Apple iPhone 11 128GB'
		];

		if ($parser->moveTo('root/products') && $parser->moveIn()) {
			do {
				if ('root/products/product' == $parser->getPath() && ($product = simplexml_load_string($parser->readOuterXml())) !== false) {
					$products[] = $product->title->__toString();
				}
			} while ($parser->moveToNextSibling());
		}

		$this->assertSame($expectedProducts, $products);
	}
}