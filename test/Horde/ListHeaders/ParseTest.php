<?php

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    ListHeaders
 * @subpackage UnitTests
 */

namespace Horde\ListHeaders\Test;

use PHPUnit\Framework\TestCase;
use Horde_ListHeaders;
use Horde_ListHeaders_Base;
use Horde_ListHeaders_Id;
use Horde_ListHeaders_NoPost;
use Horde_Mime_Headers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Horde_ListHeaders::class)]
#[CoversClass(Horde_ListHeaders_Base::class)]
#[CoversClass(Horde_ListHeaders_Id::class)]
#[CoversClass(Horde_ListHeaders_NoPost::class)]
class ParseTest extends TestCase
{
    #[DataProvider('parsingProvider')]
    public function testBaseParsing($header, $value, $urls, $comments)
    {
        $parser = new Horde_ListHeaders();
        $ob = $parser->parse($header, $value);

        $this->assertEquals(
            count($urls),
            count($ob)
        );

        foreach (array_values($urls) as $key => $val) {
            if (is_null($urls[$key])) {
                $this->assertInstanceOf(Horde_ListHeaders_NoPost::class, $ob[$key]);
                $this->assertNull($ob[$key]->url);
            } else {
                $this->assertNotInstanceOf(Horde_ListHeaders_NoPost::class, $ob[$key]);
                $this->assertEquals(
                    $urls[$key],
                    $ob[$key]->url
                );
            }

            if (empty($comments[$key])) {
                $this->assertEmpty($ob[$key]->comments);
            } else {
                foreach ($comments[$key] as $key2 => $val2) {
                    $this->assertEquals(
                        $val2,
                        $ob[$key]->comments[$key2]
                    );
                }
            }
        }
    }

    public static function parsingProvider()
    {
        return [
            [
                'list-help',
                '<mailto:list@host.com?subject=help> (List Instructions)',
                [
                    'mailto:list@host.com?subject=help',
                ],
                [
                    ['List Instructions'],
                ],
            ],
            [
                'list-help',
                '<ftp://ftp.host.com/list.txt> (FTP), <mailto:list@host.com?subject=help>',
                [
                    'ftp://ftp.host.com/list.txt',
                    'mailto:list@host.com?subject=help',
                ],
                [
                    ['FTP'],
                    [],
                ],
            ],
            [
                'list-help',
                '(Foo) <mailto:foo@example.com> (Foo2)',
                [
                    'mailto:foo@example.com',
                ],
                [
                    ['Foo', 'Foo2'],
                ],
            ],
            [
                'list-post',
                '<mailto:foo@example.com> (Foo)',
                [
                    'mailto:foo@example.com',
                ],
                [
                    ['Foo'],
                ],
            ],
            [
                'list-post',
                'NO (Foo)',
                [
                    null,
                ],
                [
                    ['Foo'],
                ],
            ],
        ];
    }

    #[DataProvider('listIdParsingProvider')]
    public function testListIdParsing($value, $id, $label)
    {
        $parser = new Horde_ListHeaders();
        $ob = $parser->parse('list-id', $value);

        $this->assertInstanceOf(Horde_ListHeaders_Id::class, $ob);
        $this->assertEquals(
            $id,
            $ob->id
        );

        if (is_null($label)) {
            $this->assertNull($ob->label);
        } else {
            $this->assertEquals(
                $label,
                $ob->label
            );
        }
    }

    public static function listIdParsingProvider()
    {
        return [
            [
                '<commonspace-users.list-id.within.com>',
                'commonspace-users.list-id.within.com',
                null,
            ],
            [
                '"Lena\'s Personal Joke List" <lenas-jokes.da39efc25c530ad145d41b86f7420c3b.021999.localhost>',
                'lenas-jokes.da39efc25c530ad145d41b86f7420c3b.021999.localhost',
                "Lena's Personal Joke List",
            ],
        ];
    }

    public function testHeadersReturnsAllRfcHeaders()
    {
        $parser = new Horde_ListHeaders();
        $headers = $parser->headers();

        $expected = [
            'list-help', 'list-unsubscribe', 'list-subscribe',
            'list-owner', 'list-post', 'list-archive', 'list-id',
        ];

        $this->assertEquals($expected, array_keys($headers));
    }

    public function testListHeadersExistReturnsTrueWhenPresent()
    {
        $mock = $this->createMock(Horde_Mime_Headers::class);
        $mock->method('offsetExists')
            ->willReturnCallback(function ($key) {
                return $key === 'list-unsubscribe';
            });

        $parser = new Horde_ListHeaders();
        $this->assertTrue($parser->listHeadersExist($mock));
    }

    public function testListHeadersExistReturnsFalseWhenAbsent()
    {
        $mock = $this->createMock(Horde_Mime_Headers::class);
        $mock->method('offsetExists')
            ->willReturn(false);

        $parser = new Horde_ListHeaders();
        $this->assertFalse($parser->listHeadersExist($mock));
    }

    public function testParseReturnsfalseForUnknownHeader()
    {
        $parser = new Horde_ListHeaders();
        $this->assertFalse($parser->parse('x-unknown', '<http://example.com>'));
    }

    public function testParseReturnsFalseForEmptyValue()
    {
        $parser = new Horde_ListHeaders();
        $this->assertFalse($parser->parse('list-help', ''));
    }
}
