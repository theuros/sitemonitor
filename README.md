# Sitemonitor snippet

Sitemonitor is used for simple monitoring of modx sites. 

It has two functionalities:

1. Gather various modx data and display it as json (encrypted if needed)
2. Read data from list of defined url-s where sitemonitor is located


## Usage

### Install it to all sites you want to watch.

1. Create a resource without a template with content type json
2. Add uncached sitemonitor snippet
3. Add optional custom data in json format
4. Set optional encryption key if you want to protect displayed data

### Install it to site where you want to track data from other sites

1. Insert comma separated list of url-s
2. Add encryption if you used in part 1

for more info check [sitemonitor page](https://similis.eu/modx/site-monitor.html)

## Plans for the future 

- cache data and results for better performance
- check extensions for upgrades
- highlight extensions used in all websites and unique ones
- add more data like: users count, last edited date, ...
- select number of rows for log preview
- add option to display log only