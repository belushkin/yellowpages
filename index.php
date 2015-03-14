<?php

// show all errors
error_reporting(E_ALL);

// specify options
$options = getopt("u:");
if (empty($options) ) {
    print "There was a problem reading in the options.\n\n";
    return false;
}

$result = array();

// get param from the options
$url = trim($options['u']);

// Load yellow pages url to the dom document
$doc = new DOMDocument();
@$doc->loadHTMLFile($url);
$xpath = new DOMXpath($doc);

// get all elements on the page
$elements = $xpath->query('//div[contains(@class, "listing")]');


function get_node(DOMNodeList $list)
{
    if ($list->length) {
        foreach ($list as $node) {
            return $node->nodeValue;
        }
    }
    return '';
}

// go over elements
$i = 0;
foreach ($elements as $element) {
    $result[$i]['name']     = get_node($xpath->query('.//h3[@class="jsMapBubbleName"]', $element));
    $result[$i]['street']   = get_node($xpath->query('.//span[@itemprop="streetAddress"]', $element));
    $result[$i]['city']     = get_node($xpath->query('.//span[@itemprop="addressLocality"]', $element));
    $result[$i]['state']    = get_node($xpath->query('.//span[@itemprop="addressRegion"]', $element));
    $result[$i]['zipcode']  = get_node($xpath->query('.//span[@itemprop="postalCode"]', $element));
    $result[$i]['phone']    = get_node($xpath->query('.//h4[contains(@class, "jsMapBubblePhone")]//span', $element));

    $list = $xpath->query('.//li[@class="visible"]//a', $element);
    if ($list->length) {
        foreach ($list as $node) {
            $result[$i]['website'] = str_replace('/gourl?','',$node->getAttribute('href'));
        }
    } else {
        $result[$i]['website'] = 'NO';
    }
    $i++;
}

print_r($result);
// Save results to a file
//$fp = fopen('results.json', 'w');
//fwrite($fp, json_encode($result));
//fclose($fp);
