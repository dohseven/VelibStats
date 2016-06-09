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
![map](https://cloud.githubusercontent.com/assets/11699669/15944399/ade94494-2e8d-11e6-9f6e-f7a961fd5bac.png)
* Click on a Velib station
![stats](https://cloud.githubusercontent.com/assets/11699669/15944398/ade70576-2e8d-11e6-9ebf-bcc738c61634.png)
* Enjoy!
 * Graph can be zoomed in or out
 * Switch between Velib number or percent

## Links

* Velib API: https://developer.jcdecaux.com/#/home
* Velib website: http://velib.paris/

## Thanks

* Inspired by the work of [Phyks](https://github.com/Phyks/VelibDataSet)
