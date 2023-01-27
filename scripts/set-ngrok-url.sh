#!/bin/sh

if [ $# -ne 2 ]; then
    echo "Usage: $0 <old-url> <new-url>"
    exit 1
fi
echo $1
echo $2
for file in samples/idp/authsources.php samples/idp/saml20-sp-remote.php samples/sp/saml20-idp-remote.php samples/sp_test/fedcmtest.php; do 
    cp $file ${file}.orig
    cat ${file}.orig | sed -e "s#$1#$2#g" > $file
    rm -f ${file}.orig
done

