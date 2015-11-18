<?php
// This script utilizes functional programming to be as thin and as simple as possible.
// It's just a database tester, not a implementation!
define('DOMAINS_DIRECTORY', realpath(__DIR__ . '/../domains'));
define('DOMAINS_DIRECTORY_LENGTH', strlen(DOMAINS_DIRECTORY));
define('BLACKLIST_FILE', DOMAINS_DIRECTORY . '/_blacklist.json');
define('DOMAIN_REGEX', '/([a-z0-9][a-z0-9-]{0,61}[a-z0-9])?(?<gTLD>\.(biz|com|edu|gov|info|int|mil|name|net|org|aero|asia|cat|coop|jobs|mobi|museum|pro|tel|travel|arpa|root))?(?(gTLD)(\.(a[c-gil-oq-uwxz]|b[abd-jmnorstvwyz]|c[acdf-ik-oruvxyz]|d[ejkmoz]|e[ceghrstu]|f[ijkmor]|g[abd-ilmnp-tuwy]|h[kmnrtu]|i[delmnoq-t]|j[emop]|k[eghimnprwyz]|l[abcikr-uvy]|m[acdeghk-z]|n[acefgilopruzc]|om|p[ae-hk-nrstwy]|qa|r[eosuw]|s[a-eg-ortuvyz]|t[cdfghj-prtvwz]|u[agksyz]|v[aceginu]|w[fs]|y[etu]|z[amw]))?|(\.(a[c-gil-oq-uwxz]|b[abd-jmnorstvwyz]|c[acdf-ik-oruvxyz]|d[ejkmoz]|e[ceghrstu]|f[ijkmor]|g[abd-ilmnp-tuwy]|h[kmnrtu]|i[delmnoq-t]|j[emop]|k[eghimnprwyz]|l[abcikr-uvy]|m[acdeghk-z]|n[acefgilopruzc]|om|p[ae-hk-nrstwy]|qa|r[eosuw]|s[a-eg-ortuvyz]|t[cdfghj-prtvwz]|u[agksyz]|v[aceginu]|w[fs]|y[etu]|z[amw])))/i');

$errorsCount = 0;
if (version_compare(PHP_VERSION, '5.5.0', '<')) {
    echo 'Your PHP version is too old - you have ' . PHP_VERSION . " while 5.5.0 or higher is required.\n";
    exit(1);
}

if (DOMAINS_DIRECTORY_LENGTH === 0) {
    echo "Domains directory is not readable.\n";
    exit(1);
}

// ******************** Checking blacklist file ********************
function verifyBlacklist(array $blacklist)
{
    $errors = 0;

    foreach ($blacklist as $key => $value) {
        if (!preg_match(DOMAIN_REGEX, $key)) {
            echo "\t- Blacklist entry >>$key<< is not a valid domain name\n";
            $errors++;
        }

        if (substr($key, 0, 4) === 'www.') {
            echo "\t- Blacklist entry >>$key<< starts with bogus www. prefix\n";
            $errors++;
        }

        if (strtolower($key) !== $key) {
            echo "\t- Blacklist entry >>$key<< should be written in lower-case\n";
            $errors++;
        }

        if (!is_array($value) || !isset($value['reason'])) {
            echo "\t- Blacklist entry >>$key<< is invalid due to it's format (or missing reason)\n";
            $errors++;
        }
    }

    return $errors;
}

echo "=> Blacklist structure verification\n";
$blacklistEntries = [];
if (file_exists(BLACKLIST_FILE)) {
    $blacklist = json_decode(@file_get_contents(BLACKLIST_FILE), true);
    if (is_array($blacklist)) {
        $errorsCount += verifyBlacklist($blacklist);

        $blacklistEntries = array_combine(array_keys($blacklist), array_column($blacklist, 'reason'));

    } else {
        echo "\t- Blacklist file is damaged\n";
        $errorsCount++;
    }

} else {
    echo "\t- Blacklist file not found\n";
    $errorsCount++;
}

// ******************** Verify classic entries ********************
echo "=> Classic Swot database verification\n";
$domainsDirectory = new RecursiveDirectoryIterator(DOMAINS_DIRECTORY);
$domainsDirectoryIterator = new RecursiveIteratorIterator($domainsDirectory);
$classicDbRegexIterator = new RegexIterator(
    $domainsDirectoryIterator, '/^.+\.txt$/i', RecursiveRegexIterator::GET_MATCH
);

$classicDbEntries = [];
foreach ($classicDbRegexIterator as $entry) {
    $path = $entry[0];
    $entry = substr($path, DOMAINS_DIRECTORY_LENGTH + 1, -4);
    $domain = implode('.', array_reverse(explode('/', $entry)));

    if (!preg_match(DOMAIN_REGEX, $domain)) {
        echo "\t- Classic entry $entry (domain: $domain) is not a valid domain\n";
        $errorsCount++;
    }

    foreach ($blacklistEntries as $blacklistDomain => $reason) {
        if (substr($domain, -strlen($blacklistDomain)) === $blacklistDomain) {
            echo "\t- Classic entry $entry (domain: $domain) matched blacklist entry $blacklistDomain (reason: $reason)\n";
            $errorsCount++;
        }
    }

    if (substr($domain, 0, 4) === 'www.') {
        echo "\t- Classic entry $entry (domain: $domain) starts with bogus www. prefix\n";
        $errorsCount++;
    }

    if (strtolower($domain) !== $domain) {
        echo "\t- Classic entry $entry (domain: $domain) should be written in lower-case\n";
        $errorsCount++;
    }

    $entryContents = @file_get_contents($path);
    if (empty($entryContents)) {
        echo "\t- Classic entry $entry.txt is empty or not readable\n";
        $errorsCount++;
    }

    $classicDbEntries[$entry] = trim($entryContents);
}

// ******************** Verify SwotNG entries ********************
echo "=> SwotNG database verification\n";

function validateIso8601($date)
{
    return (preg_match(
                '/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/',
                $date
            ) > 0);
}

function verifyNgFields($entryName, array $entryContents)
{
    static $allowedFields = [
        'name',
        'is-wildcard',
        'local-name',
        'country',
        'type',
        'added',
        'modified',
        'automatic-validation',
        'categories',
        'users',
        'blacklist',
        'website',
        'verification',
        'locations'
    ];
    static $allowedTypes = ['public', 'private'];
    static $allowedCategories = ['university', 'college', 'high-school'];
    static $allowedUserTypes = ['teachers', 'students', 'staff', 'alumni'];
    static $allowedBlacklistTypes = ['starts-with', 'ends-with'];

    $errors = 0;
    $isWildcard = false;

    if (isset($entryContents['name'])) {
        if (!is_string($entryContents['name'])) {
            echo "\t- NG entry $entryName 'name' field is " . gettype($entryContents['name']) . " - expected string\n";
            $errors++;
        }

        if (empty(trim($entryContents['name']))) {
            echo "\t- NG entry $entryName 'name' field is present but empty\n";
            $errors++;
        }

    } else {
        echo "\t- NG entry $entryName lacks required 'name' field\n";
        $errors++;
    }

    if (!isset($entryContents['is-wildcard']) || !is_bool($entryContents['is-wildcard'])) {
        echo "\t- NG entry $entryName lacks required 'is-wildcard' field (or it's not bool)\n";
        $errors++;

    } else {
        $isWildcard = $entryContents['is-wildcard'];
    }

    if (isset($entryContents['local-name'])) {
        if ($isWildcard) {
            echo "\t- NG entry $entryName is marked as wildcard - 'local-name' field doesn't make sense here\n";
            $errors++;

        } else {
            if (!is_string($entryContents['local-name'])) {
                echo "\t- NG entry $entryName 'local-name' field is " . gettype($entryContents['local-name']) .
                     " - expected string\n";
                $errors++;
            }

            if (empty(trim($entryContents['local-name']))) {
                echo "\t- NG entry $entryName 'local-name' field is present but empty\n";
                $errors++;
            }
        }
    }

    if (isset($entryContents['country'])) {
        if (!is_string($entryContents['country'])) {
            echo "\t- NG entry $entryName 'country' field is " . gettype($entryContents['country']) .
                 " - expected string\n";
            $errors++;
        }

        if (empty(trim($entryContents['country']))) {
            echo "\t- NG entry $entryName 'country' field is present but empty\n";
            $errors++;
        }

        if (strlen($entryContents['country']) !== 2) {
            echo "\t- NG entry $entryName 'country' field should contain 2 letters country code\n";
            $errors++;
        }

        if (strtoupper($entryContents['country']) !== $entryContents['country']) {
            echo "\t- NG entry $entryName 'country' field should be written uppercase\n";
            $errors++;
        }
    }

    if (isset($entryContents['types'])) {
        if (is_array($entryContents['types'])) {
            foreach ($entryContents['types'] as $type) {
                if (!in_array($type, $allowedTypes)) {
                    echo "\t- NG entry $entryName 'types' fields contains unknown entry >>$type<<\n";
                    $errors++;
                }
            }

            if (count(array_unique($entryContents['types'])) !== count($entryContents['types'])) {
                echo "\t- NG entry $entryName 'types' fields contains duplicated\n";
                $errors++;
            }

        } else {
            echo "\t- NG entry $entryName 'types' field is " . gettype($entryContents['types']) . " - expected array\n";
            $errors++;
        }
    }

    if (isset($entryContents['added'])) {
        if (!is_string($entryContents['added'])) {
            echo "\t- NG entry $entryName 'added' field is " . gettype($entryContents['added']) .
                 " - expected string\n";
            $errors++;
        }

        if (empty(trim($entryContents['added']))) {
            echo "\t- NG entry $entryName 'added' field is present but empty\n";
            $errors++;
        } elseif (!validateIso8601($entryContents['added'])) {
            echo "\t- NG entry $entryName 'added' field should contain ISO 8601 date - invalid value detected\n";
            $errors++;
        }

    } else {
        echo "\t- NG entry $entryName lacks required 'added' field\n";
        $errors++;
    }

    if (isset($entryContents['modified'])) {
        if (!is_string($entryContents['modified'])) {
            echo "\t- NG entry $entryName 'modified' field is " . gettype($entryContents['modified']) .
                 " - expected string\n";
            $errors++;
        }

        if (empty(trim($entryContents['modified']))) {
            echo "\t- NG entry $entryName 'modified' field is present but empty\n";
            $errors++;
        } elseif (!validateIso8601($entryContents['modified'])) {
            echo "\t- NG entry $entryName 'modified' field should contain ISO 8601 date - invalid value detected\n";
            $errors++;
        }

    } else {
        echo "\t- NG entry $entryName lacks required 'modified' field\n";
        $errors++;
    }

    if (isset($entryContents['automatic-validation'])) {
        if (!is_string($entryContents['automatic-validation'])) {
            echo "\t- NG entry $entryName 'automatic-validation' field is " .
                 gettype($entryContents['automatic-validation']) . " - expected string\n";
            $errors++;
        }

        if (empty(trim($entryContents['automatic-validation']))) {
            echo "\t- NG entry $entryName 'automatic-validation' field is present but empty\n";
            $errors++;
        } elseif (!validateIso8601($entryContents['automatic-validation'])) {
            echo "\t- NG entry $entryName 'automatic-validation' field should contain ISO 8601 date - invalid value detected\n";
            $errors++;
        }

    }

    if (isset($entryContents['categories'])) {
        if (is_array($entryContents['categories'])) {
            foreach ($entryContents['categories'] as $type) {
                if (!in_array($type, $allowedCategories)) {
                    echo "\t- NG entry $entryName 'categories' fields contains unknown entry >>$type<<\n";
                    $errors++;
                }
            }

            if (count(array_unique($entryContents['categories'])) !== count($entryContents['categories'])) {
                echo "\t- NG entry $entryName 'categories' fields contains duplicated\n";
                $errors++;
            }

        } else {
            echo "\t- NG entry $entryName 'categories' field is " . gettype($entryContents['categories']) .
                 " - expected array\n";
            $errors++;
        }
    }

    if (isset($entryContents['users'])) {
        if (is_array($entryContents['users'])) {
            foreach ($entryContents['users'] as $type) {
                if (!in_array($type, $allowedUserTypes)) {
                    echo "\t- NG entry $entryName 'users' fields contains unknown entry >>$type<<\n";
                    $errors++;
                }
            }

            if (count(array_unique($entryContents['users'])) !== count($entryContents['users'])) {
                echo "\t- NG entry $entryName 'users' fields contains duplicated\n";
                $errors++;
            }

        } else {
            echo "\t- NG entry $entryName 'users' field is " . gettype($entryContents['users']) . " - expected array\n";
            $errors++;
        }
    }

    if (isset($entryContents['blacklist'])) {
        if (is_array($entryContents['blacklist'])) {
            foreach ($entryContents['blacklist'] as $categoryName => $entriesInCategory) {
                if (!in_array($categoryName, $allowedBlacklistTypes)) {
                    echo "\t- NG entry $entryName 'blacklist' field contains unknown category type >>$categoryName<<\n";
                    $errors++;
                }

                foreach ($entriesInCategory as $blacklistEntryName => $blacklistEntryProperties) {
                    if (empty(trim($blacklistEntryName))) {
                        echo "\t- NG entry $entryName 'blacklist' field contains record with empty name under $categoryName category\n";
                        $errors++;
                    }

                    if (is_array($blacklistEntryProperties)) {
                        if (!isset($blacklistEntryProperties['reason'])) {
                            echo "\t- NG entry $entryName 'blacklist' field contains entry $blacklistEntryName under $categoryName where properties lacks reason entry\n";
                            $errors++;
                        }

                    } else {
                        echo "\t- NG entry $entryName 'blacklist' field contains entry $blacklistEntryName under $categoryName where properties are not array\n";
                        $errors++;
                    }
                }
            }


        } else {
            echo "\t- NG entry $entryName 'blacklist' field is " . gettype($entryContents['blacklist']) .
                 " - expected array\n";
            $errors++;
        }

    } else {
        echo "\t- NG entry $entryName lacks required 'blacklist' field\n";
        $errors++;
    }

    if (isset($entryContents['website'])) {
        if ($isWildcard) {
            echo "\t- NG entry $entryName is marked as wildcard - 'website' field doesn't make sense here\n";
            $errors++;

        } else {
            if (!is_string($entryContents['website'])) {
                echo "\t- NG entry $entryName 'website' field is " . gettype($entryContents['website']) .
                     " - expected string\n";
                $errors++;
            }

            if (empty(trim($entryContents['website']))) {
                echo "\t- NG entry $entryName 'website' field is present but empty\n";
                $errors++;
            }

            if (!filter_var($entryContents['website'], FILTER_VALIDATE_URL)) {
                echo "\t- NG entry $entryName 'website' field is present but does not look like valid URL\n";
                $errors++;
            }
        }
    }

    if (isset($entryContents['verification'])) {
        if (is_array($entryContents['verification'])) {
            if (isset($entryContents['verification']['is-verified']) &&
                is_bool($entryContents['verification']['is-verified'])
            ) {
                if ($entryContents['verification']['is-verified'] &&
                    !isset($entryContents['verification']['description']) &&
                    !isset($entryContents['verification']['url'])
                ) {
                    echo "\t- NG entry $entryName 'verification' contains 'is-verified' = true entry, but lacks required option 'description' or 'url'\n";
                    $errors++;
                }

            } else {
                echo "\t- NG entry $entryName 'verification' field lacks required option 'is-verified' (or it's not bool)\n";
                $errors++;
            }

        } else {
            echo "\t- NG entry $entryName 'blacklist' field is " . gettype($entryContents['blacklist']) .
                 " - expected array\n";
            $errors++;
        }

    } else {
        echo "\t- NG entry $entryName lacks required 'verification' field\n";
        $errors++;
    }

    if (isset($entryContents['locations'])) {
        if (is_array($entryContents['locations'])) {
            foreach ($entryContents['locations'] as $location) {
                if (!is_array($location)) {
                    echo "\t- NG entry $entryName 'locations' field contains invalid location (non-array)\n";
                    $errors++;
                }

                //Since I feel locations format is not fully baked there're no further checks
            }

        } else {
            echo "\t- NG entry $entryName 'locations' field is " . gettype($entryContents['blacklist']) .
                 " - expected array\n";
            $errors++;
        }

    }

    $extraFields = array_diff(array_keys($entryContents), $allowedFields);
    if (!empty($extraFields)) {
        echo "\t- NG entry $entryName contains some extra fields which are not withing specification: " .
             implode(', ', $extraFields) . "\n";
        $errors++;
    }

    return $errors;
}

$ngDbRegexIterator = new RegexIterator(
    $domainsDirectoryIterator, '/^.+\.json$/i', RecursiveRegexIterator::GET_MATCH
);

$ngDbEntries = [];
foreach ($ngDbRegexIterator as $entry) {
    $path = $entry[0];

    if ($path === BLACKLIST_FILE) {
        continue;
    }

    $entry = substr($path, DOMAINS_DIRECTORY_LENGTH + 1, -5);
    $domain = implode('.', array_reverse(explode('/', $entry)));

    if (!preg_match(DOMAIN_REGEX, $domain)) {
        echo "\t- NG entry $entry (domain: $domain) is not a valid domain\n";
        $errorsCount++;
    }

    if (substr($domain, 0, 4) === 'www.') {
        echo "\t- NG entry $entry (domain: $domain) starts with bogus www. prefix\n";
        $errorsCount++;
    }

    if (strtolower($domain) !== $domain) {
        echo "\t- NG entry $entry (domain: $domain) should be written in lower-case\n";
        $errorsCount++;
    }

    $entryContents = @json_decode(@file_get_contents($path), true);
    $ngDbEntries[$entry] = (isset($entryContents['name'])) ? $entryContents['name'] : '';

    if (empty($entryContents) || !is_array($entryContents)) {
        echo "\t- NG entry $entry.json is empty or not readable\n";
        $errorsCount++;
        continue;
    }

    $errorsCount += verifyNgFields($entry, $entryContents);
}

// ******************** Consistency between classic and SwotNG entries ********************
echo "=> Checking consistency between classic & NG databases\n";
foreach ($classicDbEntries as $classicEntryName => $classicEntryValue) {

    if (!isset($ngDbEntries[$classicEntryName])) {
        // echo "\t- Entry $classicEntryName lacks NG record\n";
        unset($ngDbEntries[$classicEntryName]);
        $errorsCount++;

    } else if ($classicEntryValue !== $ngDbEntries[$classicEntryName]) {
        echo "\t- Name for entry $classicEntryName differ between classic and NG format\n";
        $errorsCount++;
    }

    unset($ngDbEntries[$classicEntryName]);
}


foreach ($ngDbEntries as $ngEntryName => $ngEntryValue) { //Some entries left in ng-db which means they aren't present in classic
    echo "\t- Entry $ngEntryName lacks classic record\n";
    $errorsCount++;
}


// ******************** Return test value ********************
if ($errorsCount === 0) {
    echo "\nDatabase looks fine.\n";
    exit(0);

} else {
    echo "\nFound $errorsCount errors - database is broken!\n";
    exit(1);
}
