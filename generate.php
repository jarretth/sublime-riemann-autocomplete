<?php

function addUseful(&$config) {
    foreach(array('metric','host', 'service', 'state',) as $h) {
        $config['completions'][] = $h;
    }

}

function getEndpoint($endpoint) {
    $html = file_get_contents('http://riemann.io/api/'.$endpoint);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    return $doc;
}

function getNamespaces() {
    $index = getEndpoint('index.html');
    $sidebar = $index->getElementById('namespaces');
    $ns = array();
    foreach($sidebar->childNodes as $c) {
        if($c->tagName == 'ul') {
            foreach($c->childNodes as $listEl) {
                foreach($listEl->childNodes as $a) {
                    if($a->tagName == 'a') {
                        $ns[$a->textContent] = $a->getAttribute('href');
                    }
                }
            }
        }
    }
    return $ns;
}

function getNamespaceFunctions($name, $endpoint) {
    $index = getEndpoint($endpoint);
    $functions = array();
    foreach($index->getElementsByTagName('code') as $codeEl) {
        if (empty($codeEl->textContent)) continue;
        $fn = $codeEl->textContent;
        $fn = ltrim($fn,'(');
        $fn = rtrim($fn,')');
        $functions[] = $fn;
    }
    return $functions;
}

function addNamespaceFunctionsToConfig(&$config,$name,$functions) {
    foreach($functions as $function) {
        $fnPieces = explode(' ', $function);
        $fnName = array_shift($fnPieces);
        if($name != 'riemann.config') {
            $fnName = sprintf('%s/%s',substr($name, strlen('riemann.')),$fnName);
        }
        $trigger = sprintf("%s\t%s",$fnName, implode(' ', $fnPieces));
        if (!empty($fnPieces)) {
            $contents = $fnName;
            foreach($fnPieces as $n => $p) {
                $contents .= sprintf(' ${%d:%s}',$n+1,$p);
            }
            $config['completions'][]  = array(
                'trigger' => $trigger,
                'contents' => $contents
            );
        } else {
            $config['completions'][]  = $fnName;
        }
    }
}

function addApi(&$config) {
    $root = getNamespaces();
    foreach($root as $ns => $href) {
        $fns = getNamespaceFunctions($ns,$href);
        addNamespaceFunctionsToConfig($config,$ns,$fns);
    }
}

function writeConfig($config, $filename) {
    $file = fopen($filename, 'w');
    fwrite($file, json_encode($config));
    fclose($file);
}

$config = array(
    'scope' => 'source.clojure',
    'completions' => array()
);

addUseful($config);
addApi($config);
writeConfig($config, 'riemann.sublime-completions');
