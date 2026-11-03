<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp;

use stdClass;

final class ProtocolMetadataCases
{
    public static function escapedImageSources(): array
    {
        return [
            'data:image/svg+xml;base64,PHN2Zy8%2B',
            'data:image/svg+xml;base64,Pz8%2F',
            'data:image/svg+xml;base64,aA%3D%3D',
        ];
    }

    public static function escapedImageParameters(): array
    {
        return [
            'data:image/svg+xml;profile=foo%2Fbar;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=foo%2Fbar%2Dbaz;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=%22foo;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=foo%22bar;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=foo%22;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=foo%5Cbar;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=%22foo%22bar%22;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=%22foo%5C%22;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=%22foo%20bar%22;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=%22foo%09bar%22;base64,PHN2Zy8+',
            'data:image/svg+xml;profile=%22foo%5C%22bar%22;base64,PHN2Zy8+',
        ];
    }

    public static function invalidFields(): array
    {
        return [
            ['io.modelcontextprotocol/clientCapabilities' => ['sampling' => true]],
            ['io.modelcontextprotocol/clientCapabilities' => ['roots' => []]],
            ['io.modelcontextprotocol/clientInfo' => null],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'title' => false]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'https://']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'http:icon']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'https:icon']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'http:/icon']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'https://example.test/%ZZ']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn:exa|mple']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn://example.test/path[']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn://[::::]/path']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'https://[vF.]/path']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn:example#one#two']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'websiteUrl' => 'urn:example[path]']],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'urn:icon', 'mimeType' => false]]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'urn:icon', 'theme' => 'blue']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/png;base64,iVBORw0KGgo=', 'mimeType' => false]]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/png;base64,iVBORw0KGgo=', 'theme' => 'blue']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=utf:8;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo|bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo^bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo#bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo`bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo/bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo"bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo\\bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo%20bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=%22foo%20bar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=%22foo%0Abar%22;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;profile=foo%ZZbar;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/p%2Fng;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;char%3Dset=utf-8;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=%ZZ;base64,PHN2Zy8+']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;charset=utf-8;base64,PHN2Zy8@']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,PHN2Zy8%ZZ']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,PHN2Zy8%2']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,aA%40']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,PHN2Zy8%252B']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,aA']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,a%41']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,aB==']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'data:image/svg+xml;base64,a%42%3D%3D']]]],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'urn:icon', 'sizes' => ['any', false]]]]],
            ['io.modelcontextprotocol/clientCapabilities' => ['extensions' => ['unprefixed' => new stdClass()]]],
            ['progressToken' => null],
            ['io.modelcontextprotocol/logLevel' => true],
            ['invalid/key/' => 'value'],
            ['traceparent' => true],
            ['tracestate' => true],
            ['baggage' => true],
            ['io.modelcontextprotocol/clientInfo' => ['name' => 'client', 'version' => '1.0', 'icons' => [['src' => 'javascript:alert(1)']]]],
        ];
    }
}
