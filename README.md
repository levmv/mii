## Warning

The framework was not intended to be universal or public. Source code was published on GitHub for own convenience only.
Anyhow, it's not particulry interesting in any way. Mostly ugly legacy code :)

# mii

mii - its tiny php-framework, borned as fork of Kohana, but transofrmed over time to something like Yii2 (with a big...
big stretch). About 10 years several dozen web projects are successfully working on mii. 

### Philosophy 
Main values: simplicity, speed, minimal overhead. 

The whole story of mii's development is search for a balance/compromise between ease of development and the qualities above.
So, conceptual purity, versatility, good design patterns - all this is not about mii :) But serving 100-300k visitors
on 5 bucks vps without any fullpage cache? Easy! :)

It had some original ideas, i.e. about organizing templates in blocks system with some naming conventions (for css). 

### Requirements

Since mii 1.12: php8.1+. And php-mysqli for db (Arguable decision many years ago to support only mysql, but not once there
were a need to migrate to another db in real project). 
