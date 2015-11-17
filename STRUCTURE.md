# SwotNG data structure

New database structure has been created after many hours of thinking. The core concepts here are:
  * Tools created for original Swot should be able to read new format
  * Easy to implement in any programming language
  * Future-proof / easy to extend
  * Scalable

### It's all about filesystem
All institutions domains are stored inside filesystem structure under `domains` directory. Structure is nested in reverse domain order starting with [TLD](http://en.wikipedia.org/wiki/Top-level_domain).
In example if you want details about `asp.waw.pl` domain you should look under `domains/pl/waw/asp.json`.  

#### Backward compatibility with Swot database
It's possible to use scripts designed for [Swot](https://github.com/leereilly/swot). Folder `domains` is direct replacement for `lib/domains/`.
However keep in mind support will be limited. Original scripts are not designed to:
  * Read wildcard entries
  * Detect blacklisted domains
  * Detect blacklisted e-mail addresses
  * Provide information other than name of an institution
  
#### Wildcard entries
SwotNG supports wildcard entries. Is widely known domains ending with `.ac.uk` can be assigned for educational institutions registered in UK. Wildcard entry for `.ac.uk` was defined in `domains/uk/ac.json`.

#### Blacklisted domains
Some applications may require checking if domain wasn't found in database or it's just blacklisted. Example of that situation may be showing user information about reason of rejection: if someone tries to register using blacklisted domain it's better to display information about that fact instead of directing him to contact staff.   
Every blacklisted domain is listed inside `domains/_blacklist.json` file. Every entry currently contains only single key named `reason`.

#### Blacklisted e-mail addresses
Some institutions have widely available addresses such as `staff` or `contact` - such e-mails should not be used for validation, since they can lead to social-engineering attacks. It's easier then you may think to convince school secretary to forward you an email starting with "Hello John Doe" if you call and identify yourself as "John Doe" 5 minutes after e-mail arrival ;)  
To verify if e-mail was blacklisted open school file, e.g. `domains/pl/waw/asp.json` and loop through `[blacklist][starts-with]` and `[blacklist][ends-with]` entries checking if `john.doe` begins/ends with any of them.

#### Additional school information
Currently school file (e.g. `domains/pl/waw/asp.json`) format is not closed, but defines general data structure:
```json
{
    "name": "Fine Arts Academy in Warsaw",
    "is-wildcard": false,
    "local-name": "Akademia Sztuk Pięknych w Warszawie",
    "country": "PL",
    "types": ["public"],
    "added": "2004-02-12T15:19:21+00:00",
    "modified": "2004-02-12T15:19:21+00:00",
    "automatic-validation": "2004-02-12T15:19:21+00:00",
    "categories": ["university"],
    "users": ["students", "teachers", "staff"],
    "blacklist": {
        "starts-with": {
            "strona": {
                "reason": "Webmaster address"
            },
            "rektorat": {
                "reason": "Contact e-mail"
            }           
        },
        "ends-with": {}
    },
    "website": "http://asp.waw.pl/",
    "verification": {
        "is-verified": true,
        "url": "https://polon.nauka.gov.pl/opi/aa/rejestry/szkolnictwo",
        "description": "On given website enter Akademia Sztuk Pięknych under Nazwa field, click button named Szukaj."
    },
    "locations": [
        {
            "address": "Krakowskie Przedmieście 5, 00-068 Warszawa, Polska",
            "gps": [52.2411513,21.0114492],
            "phone": "+48 22 826 21 14"
        }
    ]
}
```

Fields description:
  * `name*` - International name using ASCII characters.
  * `is-wildcard*` - Mandatory field containing information if current entry is single institution or wildcard entry.
  * `local-name` - Name of school as appears on official documents.
  * `country` - [ISO 3166-1 alpha-2](https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2) country code.
  * `types` - Array, field denotes if it's `public` or `private` school. Since it's array it may contain both values.
  * `added*` - Contains [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) date and time of entry creation.
  * `modified*` - Contains [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) date and time of last modification of an entry. If entry was never modified date will be the same as creation.
  * `automatic-validation` - Contains [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) date and time of last automatic verification. **This denotes only verification of domain, not institution data.**
  * `categories` - Array of all education levels provided by institution.  
  Valid values: university, college, high-school (to add more create issue as needed).  
  Keep in mind list can be empty or incomplete - this field provides information more like blacklist (e.g. I don't want to provide discounts for high-schools).
  * `users` - Array of all user categories which uses e-mails under given domain.  
  Valid values: teachers, students, staff, alumni (to add more create issue as needed).  
  Keep in mind list can be empty or incomplete - this field provides information more like blacklist (e.g. I don't want to provide discounts for alumni).
  * `blacklist*` - Object containing two keys: `starts-with` and `ends-with`. Structure of every entry under these field consists of list of prefixes/postfixes for e-mail addresses with reason inside entry. Example usage of this feature will school which give all alumni address starting with `alumni.`.
  * `website` - Institution website URL.
  * `verification*` - Object containing at least one key named `isVerified` which holds bool value denoting whatever this entry was manually verified to be real education institution. If `isVerified` is set to `true` additional entry named `description` or `url` is required. Description should contain human readable description written in english describing how to verify given school. `url` can be added instead of / along with `description` with address containing proof. 
  * `locations` - Array containing objects holding information about campuses location. Every campus can contain entries: address, gps (array with lat and lon), phone.
  
Fields marked with * are mandatory for every entry.

### Reading database
Since every programming language may require different approach rules presented there are general.
Let's imagine you have someone with e-mail: `john.doe@awesome-university.us.univ.org` and you wish to check if it's eligible for academic discount.

**If you're lazy programmer and you just want to simplest check:**
  1. Extract domain part from email: `awesome-university.us.univ.org`
  2. Check if path `domains/valid/org/univ/us/awesome-university.json` exists - it doesn't, <strike>reject it</strike> read rest of the docs ;)

**If you want to properly implement checking:**
  1. Extract domain part from email: `awesome-university.us.univ.org`
  2. Check if path `domains/valid/org/univ/us/awesome-university.json` exists - it doesn't
  3. Check if path `domains/valid/org/univ/us.json` exists - it doesn't
  4. Check if path `domains/valid/org/univ.json` exists - file was found
  5. If you want simplest check you can stop now - e-mail is valid
  6. If you specifically want to know if domain just doesn't exist or it's just banned you can open `domains/_blacklist.json` and look for entry
  7. Open `domains/valid/org/univ.json` file and loop through `[blacklist][starts-with]` and `[blacklist][ends-with]` entries checking if `john.doe` begins/ends with any of them.

When implementing new projects please, **do not** check for `.txt` files. That method is obsoleted - these files are available for backward-compatibility reasons.

 
### FAQ
##### Why JSON and not XML/YAML/other?
Json is old, stable and easy to parse. It also comes with little overhead and can be edited by-hand. Despite it's name (JavaScript Object Notation) reading it have implementation in almost any programming language.
Yaml was considered, but dropped because it's not trivial to read it in some languages and it's easy to break it's formatting. XML isn't an option due to size and processor power require to parse it.

##### Why not single JSON file?
While file with list of all domains will weight about ~140KB there's a problem with metadata. Including them will grow file uncontrollably.

##### Why not single JSON file per domain?
Primary reason is backward compatibility with Swot. Second reason is performance - some filesystems can hold no more than e.g. 4096 files in directory, some if not most of them (e.g. widely used ext3 at about 10 000 files) starts to slow down if more then reasonable amount of files are present in single directory.

##### So maybe you should provide few formats?
While it sounds like a good compromise it's not. While reading such database will be easy maintaining it will not be. If you desperately want another format you should use scripts available in separate repository to generate it.

##### Starts-with/ends-with can be presented as regex
Yes, it's true. However there're few problems with them:
  * Syntax differ between languages
  * They're generally slow
  * Regexes are tend to be bug-producers ;)
