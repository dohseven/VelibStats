VelibStats
==========

This code can be used to dump periodically and store all the available data from the Velib API. These data are under an OpenData license.
The history statistic can then be consulted on a web page.

## Requirements

* PHP server
* MySQL server

## Usage

* Clone this repository
* Create an account to access the Velib API: https://developer.jcdecaux.com/#/home
* Update the `param.php` file with your parameters:
 * Set your API key
 * Set your SQL parameters

### Data update

* Run `php update.php`

### Data consultation

* Open `index.html`
* Click on a Velib station

## Links

* Velib API: https://developer.jcdecaux.com/#/home
* Velib website: http://velib.paris/

## Thanks

* Inspired by the work of [Phyks](https://github.com/Phyks/VelibDataSet)
