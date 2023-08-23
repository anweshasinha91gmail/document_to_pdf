<?php
declare(strict_types=1);

namespace tmitry\XMLReaderExtension;

use XMLReader;
use BadMethodCallException;

class XMLParser
{
	private $xmlReader;
	private $path = [];

	public function __construct(XMLReader $xmlReader = null)
	{
		if (is_null($xmlReader)) {
			$xmlReader = new XMLReader();
		}

		$this->xmlReader = $xmlReader;
	}

	public function __call($name, $args)
	{
		if (is_callable([$this->xmlReader, $name], true)) {
			return call_user_func([$this->xmlReader, $name], $args);
		}

		throw new BadMethodCallException();
	}

	public static function __callStatic($name, $args)
	{
		if (is_callable([XMLReader::class, $name, true])) {
			return call_user_func([XMLReader::class, $name], $args);
		}

		throw new BadMethodCallException();
	}

	public function open(string $fileName, $encoding = null): bool
	{
		// prevent XXE attacks
		return $this->xmlReader->open($fileName, $encoding, LIBXML_NONET);
	}

	public function moveTo(string $path): bool
	{
		while ($this->xmlReader->read()) {
            if ($this->xmlReader->nodeType == XMLReader::END_ELEMENT) {
                array_pop($this->path);
                continue;
            }

            if ($this->xmlReader->nodeType !== XMLReader::ELEMENT || $this->xmlReader->isEmptyElement) {
                continue;
            }

            array_push($this->path, $this->xmlReader->name);

            if ($path === $this->getPath()) {
				return true;
			}
        }

        return false;
	}

	public function getPath(): string
	{
		return implode("/", $this->path);
	}

	public function moveIn(): bool
	{
        if ($this->xmlReader->isEmptyElement) {
            return false;
        }
        while ($this->xmlReader->read()) {
            if (XMLReader::ELEMENT == $this->xmlReader->nodeType) {
                array_push($this->path, $this->xmlReader->name);
                return true;
            }

            if (XMLReader::END_ELEMENT == $this->xmlReader->nodeType) {
                array_pop($this->path);
                return false;
            }
        }

        return false;
	}

	public function moveToNextSibling(): bool
	{
        array_pop($this->path);
        while ($this->xmlReader->next()) {
            if (XMLReader::ELEMENT == $this->xmlReader->nodeType) {
                array_push($this->path, $this->xmlReader->name);
                return true;
            }

            if (XMLReader::END_ELEMENT == $this->xmlReader->nodeType) {
                return false;
            }
        }

        return false;
	}
}