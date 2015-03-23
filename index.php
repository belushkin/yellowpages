<?php

if (isset($_POST['zoho'])) {
    //$xmlData = '<Leads><row no="1"><FL val="Lead Source">Web Download</FL><FL val="Company">Your Company</FL><FL val="First Name">Hannah</FL><FL val="Last Name">Smith</FL><FL val="Email">testing@testing.com</FL><FL val="Title">Manager</FL><FL val="Phone">1234567890</FL><FL val="Home Phone">0987654321</FL><FL val="Other Phone">1212211212</FL><FL val="Fax">02927272626</FL><FL val="Mobile">292827622</FL></row></Leads>';
    $token  = "6b5f27d96b653e4306c0eafb83fdde70";
    $url    = "https://crm.zoho.com/crm/private/xml/Leads/insertRecords";
    $param  = "authtoken=" . $token . "&scope=crmapi&xmlData=" . $_POST['xmlData'];

//    print_r($_POST);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
    $result = curl_exec($ch);
    curl_close($ch);
    echo $result;
    return true;
} else { ?>
    <html>

    <head>
        <title>Yellow pages parser</title>
        <script type="text/javascript" src="https://code.jquery.com/jquery-1.10.2.js"></script>
    </head>
    <body>

    <style>
        .spinner {background: #00FFD9 url(ajax-loader.gif) no-repeat left top ;}
    </style>

    <form method="post">
        <span>Enter URL here:</span>
        <input style="width:600px" type="text" name="url" value="<?php echo (isset($_POST['url'])) ? $_POST['url'] : '';?>">
        <input type="submit" value="Submit">
        <div id="exported">WORKING</div>
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

    $xml    = '';
    $i      = 0;
    if (isset($_POST['url']) && !empty($_POST['url'])) {
        $url = trim($_POST['url']);
        $result = array();
        $temp   = array();

        $doc = new DOMDocument();
        @$doc->loadHTMLFile($url);
        $xpath = new DOMXpath($doc);

        $elements = $xpath->query('//div[contains(@class, "listing")]');

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
        $xml = '<Leads>';
        foreach ($result as &$item) {
            echo $i;
            $xml .= '<row no="'.$i.'">';
            $item['Website']            = ($item['site'] == 'NO') ? 'NO' : 'YES';
            $item['Desktop']            = ($item['site'] == 'NO') ? 'NO' : 'YES';
            $item['errors']             = array();
            $item['mobile_friendly']    = 'NO';

            // OUTPUT
            echo "<table border=1 width=400 bgcolor='#00FFD9' cellpadding='10' cellspacing='10'><tr><td>";

            echo "<p><strong>Company:</strong> {$item['name']}</p>";$xml    .= '<FL val="Company">'.$item['name'].'</FL>';
            echo "<p><strong>Phone:</strong> {$item['phone']}</p>";$xml     .= '<FL val="Phone">'.$item['phone'].'</FL>';
            echo "<p><strong>Street:</strong> {$item['street']}</p>";$xml   .= '<FL val="Street">'.$item['street'].'</FL>';
            echo "<p><strong>City:</strong> {$item['city']}</p>";$xml       .= '<FL val="City">'.$item['city'].'</FL>';
            echo "<p><strong>State:</strong> {$item['state']}</p>";$xml     .= '<FL val="State">'.$item['state'].'</FL>';
            echo "<p><strong>Zipcode:</strong> {$item['zipcode']}</p>";$xml .= '<FL val="Zip Code">'.$item['zipcode'].'</FL>';
            echo "<p><strong>Website:</strong> {$item['Website']}</p>";$xml .= '<FL val="Website">'.$item['site'].'</FL>';
            echo "<p><strong>Desktop:</strong> {$item['Desktop']}</p>";$xml .= '<FL val="Description">Desktop:'.$item['Desktop'].', Website: '.$item['Website'].'</FL>';
            echo "<p><strong>Site:</strong> <span class='site'>{$item['site']}</span></p>";

            echo "</td></tr></table><br/>";
            $xml .= '</row>';
            $i++;
            if ($i == 3) {
                break;
            }
        }
        $xml .= '</Leads>';
    }
    echo "<div id='xml'>{$xml}</div>";
    echo "<div id='count'>{$i}</div>";
    //exit();
    ?>
    </body>

    <script type="text/javascript">
        $( document ).ready(function() {
            var xml = $($.parseXML($("#xml").html()));
            var i = 1;
            var count = $("#count").text();
            var exported = $("#exported");

            $('.site').each(function() {
                var url = $(this).text();
                var span = $(this);
                var string = '';
                if (url != 'NO') {
                    span.closest('table', span).addClass('spinner');
                    $.ajax({
                        url: 'https://www.googleapis.com/pagespeedonline/v3beta1/mobileReady?url=' + url,
                        dataType: "json",
                        success: function( data ) {
                            if (data.responseCode == '200') {
                                if (data.ruleGroups.USABILITY.pass) {
                                    span.parent().parent().append( "<p><strong>Mobile Friendly:</strong> YES</p>" );
                                    string += ', Mobile Friendly: YES';
                                } else {
                                    span.parent().parent().append( "<p><strong>Mobile Friendly:</strong> NO</p>" );
                                    string += ', Mobile Friendly: NO';
                                    if (data.formattedResults.ruleResults.ConfigureViewport.ruleImpact > 1) {
                                        span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.ConfigureViewport.localizedRuleName + "</p>" );
                                        string += ', ' + data.formattedResults.ruleResults.ConfigureViewport.localizedRuleName;
                                    }
                                    if (data.formattedResults.ruleResults.UseLegibleFontSizes.ruleImpact > 1) {
                                        span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.UseLegibleFontSizes.localizedRuleName + "</p>" );
                                        string += ', ' + data.formattedResults.ruleResults.UseLegibleFontSizes.localizedRuleName;
                                    }
                                    if (data.formattedResults.ruleResults.AvoidPlugins.ruleImpact > 1) {
                                        span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.AvoidPlugins.localizedRuleName + "</p>" );
                                        string += ', ' + data.formattedResults.ruleResults.AvoidPlugins.localizedRuleName;
                                    }
                                    if (data.formattedResults.ruleResults.SizeContentToViewport.ruleImpact > 1) {
                                        span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.SizeContentToViewport.localizedRuleName + "</p>" );
                                        string += ', ' + data.formattedResults.ruleResults.SizeContentToViewport.localizedRuleName;
                                    }
                                    if (data.formattedResults.ruleResults.SizeTapTargetsAppropriately.ruleImpact > 1) {
                                        span.parent().parent().append( "<p>" + data.formattedResults.ruleResults.SizeTapTargetsAppropriately.localizedRuleName + "</p>" );
                                        string += ', ' + data.formattedResults.ruleResults.SizeTapTargetsAppropriately.localizedRuleName;
                                    }

                                }
                            } else {
                                span.parent().parent().append( "<p><strong>Mobile Friendly:</strong> NO</p>" );
                                string += ', Mobile Friendly: NO';
                            }
                            span.closest('table', span).removeClass('spinner');
                            addNode(url, string);
                            i++;
                            console.log(i,parseInt(count), "success");
                            if (i == parseInt(count)) {
                                exported.text('DONE');
                                sendXml();
                            }
                        },
                        error: function( data ) {
                            span.closest('table', span).removeClass('spinner');
                            span.parent().parent().append( "<p><strong>Mobile Friendly:</strong> NO</p>" );
                            string += ', Mobile Friendly: NO';
                            addNode(url, string);
                            i++;
                            console.log(i,parseInt(count), "error");
                            if (i == parseInt(count)) {
                                exported.text('DONE');
                                sendXml();
                            }
                        }
                    });
                }
            })
        });

        function addNode(url, string) {
            $($(xml).find('fl[val="Website"]')).each(function(){
                if ($(this).text() == url) {
                    var str = $($(this).next()[0]).text();
                    $($(this).next()[0]).text(str + string);
                }
            })
        }

        function sendXml() {
            var xml = $("#xml").html();
            $.ajax({
                url: 'http://auto-in-ato.com.ua/',
                method: "POST",
                dataType: "text",
                data: { xmlData: xml, "zoho":1 },
                success: function( data ) {
                },
                error: function( data ) {
                }
            });
        }
    </script>
    </html> <?php
}

