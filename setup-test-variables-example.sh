#!/bin/bash
#
# One of the tests actually requires communicating with AWS SES.  To assign the necessary variables,
# you can run this script with the correct values.  Be sure you don't commit your keys to the repo,
# but instead copy this to setup-test-variables.sh and put your values there.
# 
export AWS_ACCESS_KEY_ID=
export AWS_SECRET_ACCESS_KEY=
export FROM_EMAIL=
