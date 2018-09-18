#!/bin/bash
rsync ./ dpayexplorer2:/var/www/com_dpayexplorer/ --rsh ssh --rsync-path="sudo rsync" --recursive --perms --delete --verbose --exclude=.git* --exclude=docker/**/*.service --exclude=app/configs/*.php --exclude=cache --exclude=vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer/ --exclude=vendor --exclude=app/config --exclude=app/storage/views/*.php --checksum -a
