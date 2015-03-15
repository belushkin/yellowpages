<?php

// show all errors
error_reporting(E_ALL);

// The maximum execution time, in seconds. If set to zero, no time limit is imposed.
set_time_limit(0);

// specify options
$options = getopt("u:");
if (empty($options) ) {
    print "There was a problem reading in the options.\n\n";
    return false;
}

$result = array();
$temp   = array();

// get param from the options
$url = trim($options['u']);

echo "\n Load yellow pages url to the dom document\n";

$doc = new DOMDocument();
@$doc->loadHTMLFile($url);
$xpath = new DOMXpath($doc);

echo "\n Get all elements on the page\n";

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

echo "\n Go over elements\n";

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
            $result[$i]['site'] = str_replace('/gourl?','',$node->getAttribute('href'));
        }
    } else {
        $result[$i]['site'] = 'NO';
    }
    $i++;
}

echo "\n Get only unique results\n";

for ($k = 0; $k < count($result); $k++) {
    $temp[md5(implode("",array_values($result[$k])))] = $result[$k];
}
$result = $temp;

if (empty($result)) {
    print "No data for parsing";
    return false;
}

echo "\n Start parsing mobile info: \n";

foreach ($result as &$item) {
    if ($item['site'] == 'NO') {
        $item['Desktop'] = 'NO';
        $item['Website'] = 'NO';
        $item['mobile_friendly'] = 'NO';
        continue;
    }

    $item['Website']    = 'YES';
    $item['Desktop']    = 'YES';
    $item['errors']     = '';

    echo "\n Query: {$item['site']}\n";
    $q = file_get_contents("https://www.googleapis.com/pagespeedonline/v3beta1/mobileReady?url={$item['site']}");
    if (!$q) {
        sleep(1);
        continue;
    }
    $json = json_decode($q);

    if ($json->responseCode != 200) {
        echo "\n Google responded with bad result (WebSite probably not answering), {$item['site']}!!!!!!\n";
        $item['mobile_friendly'] = 'UNDEFINED';
        sleep(1);
        continue;
    }
    if ((bool)$json->ruleGroups->USABILITY->pass) {
        $item['mobile_friendly'] = 'YES';
    } else {
        $item['mobile_friendly'] = 'NO';

        if ($json->formattedResults->ruleResults->ConfigureViewport->ruleImpact > 1) {
            $item['errors'][] = $json->formattedResults->ruleResults->ConfigureViewport->localizedRuleName;
        }
        if ($json->formattedResults->ruleResults->UseLegibleFontSizes->ruleImpact > 1) {
            $item['errors'][] = $json->formattedResults->ruleResults->UseLegibleFontSizes->localizedRuleName;
        }
        if ($json->formattedResults->ruleResults->AvoidPlugins->ruleImpact > 1) {
            $item['errors'][] = $json->formattedResults->ruleResults->AvoidPlugins->localizedRuleName;
        }
        if ($json->formattedResults->ruleResults->SizeContentToViewport->ruleImpact > 1) {
            $item['errors'][] = $json->formattedResults->ruleResults->SizeContentToViewport->localizedRuleName;
        }
        if ($json->formattedResults->ruleResults->SizeTapTargetsAppropriately->ruleImpact > 1) {
            $item['errors'][] = $json->formattedResults->ruleResults->SizeTapTargetsAppropriately->localizedRuleName;
        }
    }
    sleep(1);
}
echo "\n BUILDING HTML RESULTS\n";

$str = "<html><head></head><body>";

foreach ($result as &$item) {
    $str .= "<table border=1 width=400 bgcolor='#00FFD9' cellpadding='10' cellspacing='10'><tr><td><table><tr><td>Record<hr/></td></tr>";

    $str .= "<tr><td>Company:</td><td>{$item['name']}</td></tr>";
    $str .= "<tr><td>Phone:</td><td>{$item['phone']}</td></tr>";
    $str .= "<tr><td>Street:</td><td>{$item['street']}</td></tr>";
    $str .= "<tr><td>City:</td><td>{$item['city']}</td></tr>";
    $str .= "<tr><td>State:</td><td>{$item['state']}</td></tr>";
    $str .= "<tr><td>Zipcode:</td><td>{$item['zipcode']}</td></tr></table></td></tr>";

    $str .= "<tr><td><table><tr><td>Website Data<hr/></td></tr>";
    $str .= "<tr><td>Website:</td><td>{$item['Website']}</td></tr>";
    $str .= "<tr><td>Desktop:</td><td>{$item['Desktop']}</td></tr>";
    $str .= "<tr><td>Mobile Friendly:</td><td>{$item['mobile_friendly']}</td></tr></table></td></tr>";

    if ($item['mobile_friendly'] == "NO" && $item['Website'] != "NO") {
        $str .= "<tr><td><table><tr><td>Results<hr/></td></tr>";
        $str .= "<tr><td><b>Page appears not mobile-friendly</b></td></tr>";
        foreach ($item['errors'] as $error) {
            $str .= "<tr><td>$error</td></tr>";
        }
        $str .= "</table></td></tr>";
    }
    $str .= "</table><br/>\n";
}
$str .= "</body></html>";

echo "\n Save results to a file\n";

$fp = fopen('results.html', 'w');
fwrite($fp, $str);
fclose($fp);
