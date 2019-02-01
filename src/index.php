<?php
function createArray(callable $callback, int $size){
    $result = [];
    for($i = 0; $i < $size; $i++){
        [$key, $value] = $callback($i);
        $result[$key] = $value;
    }
    return $result;
}

class SampleObject{
    public $i;
    public $j;
    public $l;

    /**
     * SampleObject constructor.
     * @param $i
     * @param $j
     * @param $l
     */
    public function __construct($i, $j, $l){
        $this->i = $i;
        $this->j = $j;
        $this->l = $l;
    }
}

// для Configuration, Ini, YAML, JSON, XML и serialize
$lvlOne = createArray(function($i){
    return ["key{$i}", "value{$i}"];
}, 10000);
// для Ini, YAML, JSON, XML и serialize
$lvlTwo = createArray(function($i){
    return ["key{$i}", createArray(function($j)use($i){
        return ["key{$i}-{$j}", "value{$j}"];
    }, 10)];
}, 1000);
// для YAML, JSON, XML и serialize
$lvlThree = createArray(function($i){
    return ["key{$i}", createArray(function($j)use($i){
        return ["key{$i}", createArray(function($l)use($i, $j){
            return ["key{$i}-{$j}-{$l}", "value{$l}"];
        }, 10)];
    }, 10)];
}, 1000);
// для JSON и serialize
$lvlFour = createArray(function($i){
    return ["key{$i}", createArray(function($j)use($i){
        return ["key{$i}", createArray(function($l)use($i, $j){
            return ["key{$i}-{$j}-{$l}", new SampleObject($i, $j, $l)];
        }, 10)];
    }, 10)];
}, 1000);

use php\time\Time;
use php\io\FileStream;
use php\lib\fs;

use php\util\Configuration;
use php\format\IniProcessor;
use php\xml\XmlProcessor;
use php\format\JsonProcessor;
use php\lib\str;

function timer(){
    static $time;

    if(!isset($time)){
        $time = Time::millis();
    }
    else{
        $totalTime = Time::millis() - $time;
        $time = null;

        return $totalTime;
    }
    return -1;
}

function separator(){
    echo str::repeat('-', 100)."\n";
}

function test($data, string $name, string $ext, callable $doAction){
    $out = new FileStream("results/{$name}.{$ext}", 'w');
    $in = new FileStream("results/{$name}.{$ext}");

    [$saveTime, $loadTime] = $doAction($data, $out, $in);
    $out->close();
    $in->close();
    echo "Test {$name} save time: {$saveTime}; load time: {$loadTime}\n";
}

function configurationTest($data, string $name){
    test($data, $name, 'cfg', function($data, FileStream $out, $in){
        $configuration = new Configuration();
        $configuration->put($data);
        timer();
        $configuration->save($out);
        $saveTime = timer();
        timer();
        $configuration->load($in);
        $loadTime = timer();
        return [$saveTime, $loadTime];
    });
}

function iniTest($data, string $name){
    test($data, $name, 'ini', function($data, FileStream $out, FileStream $in){
        $iniProcessor = new IniProcessor;
        timer();
        $iniProcessor->formatTo($data, $out);
        $saveTime = timer();
        timer();
        $iniProcessor->parse($in);
        $loadTime = timer();
        return [$saveTime, $loadTime];
    });
}

function xmlTest($data, string $name){
    test($data, $name, 'xml', function($data, FileStream $out, FileStream $in){
        $xmlProcessor = new XmlProcessor();
        $document = createXMLDocument('root', $data);
        timer();
        $xmlProcessor->formatTo($document, $out);
        $saveTime = timer();
        timer();
        $xmlProcessor->parse($in);
        $loadTime = timer();
        return [$saveTime, $loadTime];
    });
}

use php\xml\DomNode;
use php\xml\DomDocument;

function createXMLDocument(string $name, array $data){
    $document = (new XmlProcessor())->createDocument();
    $root = $document->createElement($name, []);
    putDataToNode($document, $root, $data);
    $document->appendChild($root);

    return $document;
}
function putDataToNode(DomDocument $document, DomNode $node, array $data){
    foreach($data as $key => $value){
        $subNode = $document->createElement($key, []);
        if(is_array($value)){
            putDataToNode($document, $subNode, $value);
        }
        else{
            $subNode->setTextContent($value);
        }
        $node->appendChild($subNode);
    }
}

function jsonTest($data, string $name){
    test($data, $name, 'json', function($data, FileStream $out, FileStream $in){
        $jsonProcessor = new JsonProcessor(\php\format\JsonProcessor::SERIALIZE_PRETTY_PRINT);
        timer();
        $jsonProcessor->formatTo($data, $out);
        $saveTime = timer();
        timer();
        $jsonProcessor->parse($in);
        $loadTime = timer();
        return [$saveTime, $loadTime];
    });
}

function yamlTest($data, string $name){
    test($data, $name, 'yaml', function($data, FileStream $out, FileStream $in){
        $yamlProcessor = new \php\format\YamlProcessor();
        timer();
        $yamlProcessor->formatTo($data, $out);
        $saveTime = timer();
        timer();
        $yamlProcessor->parse($in);
        $loadTime = timer();
        return [$saveTime, $loadTime];
    });
}

function serializeTest($data, string $name){
    test($data, $name, 'bin', function($data, FileStream $out, FileStream $in){
        timer();
        $out->write(serialize($data));
        $saveTime = timer();
        timer();
        unserialize($in->readFully());
        $loadTime = timer();
        return [$saveTime, $loadTime];
    });
}

function startTest(string $label, $lvlOne, $lvlTwo, $lvlThree, $lvlFour){
    echo "{$label}\n";
    fs::makeDir('results');
    fs::clean('results');

    // configuration
    configurationTest($lvlOne,'Configuration-1');
    separator();

    // ini
    iniTest($lvlOne, 'Ini-1');
    iniTest($lvlTwo, 'Ini-2');
    separator();

    // xml
    xmlTest($lvlOne, 'Xml-1');
    xmlTest($lvlTwo, 'Xml-2');
    xmlTest($lvlThree, 'Xml-3');
    separator();

    // yaml
    yamlTest($lvlOne, 'Yaml-1');
    yamlTest($lvlTwo, 'Yaml-2');
    yamlTest($lvlThree, 'Yaml-3');
    separator();

    // json
    jsonTest($lvlOne, 'Json-1');
    jsonTest($lvlTwo, 'Json-2');
    jsonTest($lvlThree, 'Json-3');
    jsonTest($lvlFour, 'Json-4');
    separator();

    // serialize
    serializeTest($lvlOne, 'Serialize-1');
    serializeTest($lvlTwo, 'Serialize-2');
    serializeTest($lvlThree, 'Serialize-3');
    serializeTest($lvlFour, 'Serialize-4');
}


ob_start();
startTest("First test:", $lvlOne, $lvlTwo, $lvlThree, $lvlFour);
echo "\n";
separator();
echo "\n";
startTest("Second test:", $lvlOne, $lvlTwo, $lvlThree, $lvlFour);
$content = ob_get_clean();
ob_end_flush();
$output = new FileStream('result.txt', 'w');
$output->write($content);
$output->close();
echo $content;