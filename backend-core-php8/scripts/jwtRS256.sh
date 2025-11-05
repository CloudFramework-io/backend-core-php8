# -m PEM is necessary for OSX because if it is not included it generates a OPENSSH Key
ssh-keygen -m PEM -t rsa -b 4096 -f jwtRS256.key
# Don't add passphrase
openssl rsa -in jwtRS256.key -pubout -outform PEM -out jwtRS256.key.pub
cat jwtRS256.key
cat jwtRS256.key.pub