<html>

<head>
    <title>Yellow pages parser</title>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.3/jquery.min.js"></script>
</head>
<body>

<style>
    .spinner {background: #00FFD9 url(ajax-loader.gif) no-repeat left top ;}
</style>

<form method="post">
    <span>Enter URL here:</span>
    <input style="width:600px" type="text" name="url" value="<?php echo (isset($_POST['url'])) ? $_POST['url'] : '';?>">
    <input type="submit" value="Submit">
</form>
<?php

// show all errors
error_reporting(E_ALL);

// The maximum execution time, in seconds. If set to zero, no time limit is imposed.
//set_time_limit(0);

function get_node(DOMNodeList $list)
{
    if ($list->length) {
        foreach ($list as $node) {
            return $node->nodeValue;
        }
    }
    return '';
}

if (isset($_POST['url']) && !empty($_POST['url'])) {
    $url = trim($_POST['url']);
    $result = array();
    $temp   = array();

    $doc = new DOMDocument();
    @$doc->loadHTMLFile($url);
    $xpath = new DOMXpath($doc);


    $elements = $xpath->query('//div[contains(@class, "listing")]');

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

    for ($k = 0; $k < count($result); $k++) {
        $temp[md5(implode("",array_values($result[$k])))] = $result[$k];
    }
    $result = $temp;

    if (empty($result)) {
        print "No data for parsing";
        return false;
    }

    $i = 1;
    foreach ($result as &$item) {
        echo $i;
        $item['Website']            = ($item['site'] == 'NO') ? 'NO' : 'YES';
        $item['Desktop']            = ($item['site'] == 'NO') ? 'NO' : 'YES';
        $item['errors']             = array();
        $item['mobile_friendly']    = 'NO';

        // OUTPUT
        echo "<table border=1 width=400 bgcolor='#00FFD9' cellpadding='10' cellspacing='10'><tr><td>";

        echo "<p><strong>Company:</strong> {$item['name']}</p>";
        echo "<p><strong>Phone:</strong> {$item['phone']}</p>";
        echo "<p><strong>Street:</strong> {$item['street']}</p>";
        echo "<p><strong>City:</strong> {$item['city']}</p>";
        echo "<p><strong>State:</strong> {$item['state']}</p>";
        echo "<p><strong>Zipcode:</strong> {$item['zipcode']}</p>";
        echo "<p><strong>Website:</strong> {$item['Website']}</p>";
        echo "<p><strong>Desktop:</strong> {$item['Desktop']}</p>";
        echo "<p><strong>Site:</strong> <span class='site'>{$item['site']}</span></p>";

        echo "</td></tr></table><br/>";
        $i++;
    }
}

?>
</body>

<script type="text/javascript">
    $( document ).ready(function() {
        $('.site').each(function() {
            var url = $(this).text();
            var span = $(this);
            if (url != 'NO') {
                span.closest('table', span).addClass('spinner');
                $.ajax({
                    url: 'https://www.googleapis.com/pagespeedonline/v3beta1/mobileReady?url=' + url,
                    dataType: "json",
                    success: function( data ) {
                        if (data.responseCode == '200') {
                            if (data.ruleGroups.USABILITY.pass) {
                                span.parent().parent().append( "<p><strong>Mobile Friendly:</strong> YES</p>" );
                            } else {
                                span.parent().parent().append( "<p><strong>Mobile Friendly:</strong> NO</p>" );
                                if (data.formattedResults.ruleResults.ConfigureViewport.ruleImpact > 1) {
                                    span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.ConfigureViewport.localizedRuleName + "</p>" );
                                }
                                if (data.formattedResults.ruleResults.UseLegibleFontSizes.ruleImpact > 1) {
                                    span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.UseLegibleFontSizes.localizedRuleName + "</p>" );
                                }
                                if (data.formattedResults.ruleResults.AvoidPlugins.ruleImpact > 1) {
                                    span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.AvoidPlugins.localizedRuleName + "</p>" );
                                }
                                if (data.formattedResults.ruleResults.SizeContentToViewport.ruleImpact > 1) {
                                    span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.SizeContentToViewport.localizedRuleName + "</p>" );
                                }
                                if (data.formattedResults.ruleResults.SizeTapTargetsAppropriately.ruleImpact > 1) {
                                    span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.SizeTapTargetsAppropriately.localizedRuleName + "</p>" );
                                }
                            }
                        } else {
                            span.parent().parent().append( "<p><strong>Mobile Friendly:</strong> NO</p>" );
                        }
                        span.closest('table', span).removeClass('spinner');
                    },
                    error: function( data ) {
                        span.closest('table', span).removeClass('spinner');
                    }
                });
            }
        })
    });
</script>
</html>
