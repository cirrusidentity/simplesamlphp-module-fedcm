<?php

require_once('/var/simplesamlphp/src/_autoload.php');

$spEntityId = 'https://sp.college.edu/sp';
$relayState = 'https://your-forwarding-url.ngrok.io/sample-idp/module.php/admin/test/default-sp';

// get ACS endpoint from SP metadata
$metadata = \SimpleSAML\Metadata\MetaDataStorageHandler::getMetadataHandler();
$spConfig = $metadata->getMetaDataForEntities([$spEntityId], 'saml20-sp-remote');
// var_dump($spConfig);
$acsEndpoint = $spConfig[$spEntityId]['AssertionConsumerService'][0]['Location'];
?>

<html>

<head>
    <title>FedCM SP Test</title>
</head>

<body>
    <form method="post" id="fedcmForm" action="<?= $acsEndpoint ?>">
        <input type="hidden" id="SAMLResponse" name="SAMLResponse" value="" />
        <input type="hidden" name="RelayState" value="<?= $relayState ?>" />
    </form>
    <script>
        function dec2hex (dec) {
            return dec.toString(16).padStart(2, "0")
        }

        // generateId :: Integer -> String
        function generateId (len) {
            var arr = new Uint8Array((len || 40) / 2)
            window.crypto.getRandomValues(arr)
            return Array.from(arr, dec2hex).join('')
        }

        document.addEventListener("DOMContentLoaded", function() {
            (async () => {
                const credential = await navigator.credentials.get({
                    identity: {
                        providers: [{
                            configURL: 'https://your-forwarding-url.ngrok.io/sample-idp/module.php/fedcm/manifest',
                            clientId: 'https://sp.college.edu/sp',
                            nonce: generateId(10)
                        }]
                    }
                });
                const { token } = credential;
                document.getElementById("SAMLResponse").value = token;
                document.getElementById("fedcmForm").submit();
                // console.log("*** token = ", atob(token));
            })();
        });
    </script>
</body>

</html>