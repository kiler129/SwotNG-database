# Contributing to SwotNG database

There's one rule: try not to screw it ;)

#### Adding entries - the "I don't know GitHub" way
Navigate to "Issues" tab and create new issue. It will be nice if you check if it isn't already there ;)   
You can use following issue template:
```
School name in english: 
School name in local language:
Country: 
School is public or private?
What is level of education provided in school? Is it university/high-school/college?
Who can get e-mail under school domain: 
When someone graduates can he keep existing e-mail account? 
School website address: http://
How can we verify that school exists and it's real education institution?
Where school campus/es is/are located: 
```

#### Adding entries
If you wish to add e.g. `awesome-university.us.univ.org` you should create two files:
  * domains/valid/org/univ/us/awesome-university.txt
  * domains/valid/org/univ/us/awesome-university.json
  
First one should contain english name of the school, e.g. Fine Arts Academy in Warsaw.  
Second one have to be valid JSON file containing at least following data:
```json
{
    "name": "Fine Arts Academy in Warsaw",
    "isWildcard": false,
    "added": "2004-02-12T15:19:21+00:00",
    "modified": "2004-02-12T15:19:21+00:00",
    "blacklist": {
        "starts-with": {},
        "ends-with": {}
    },
    "verification": {
        "isVerified": false,
    },
}
```

If you're able to provide additional information about your school it will be welcomed. Just entry all data you know using structure described in ["Additional school information" section of Structure description](https://github.com/kiler129/SwotNG-database/blob/master/STRUCTURE.md).

#### Editing entries
You should edit .json and/or .txt files. Please check if files are valid before commiting ;)

#### Deleting entries
Delete .txt and .json file - that's it.
