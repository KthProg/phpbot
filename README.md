phpbot
======

A fully extensible PHP IRC bot.

To extend the bot, create a new class that extends PHPBot. Add protected methods
to this class for each command you would like the bot to respond to. Each of
these protected methods will take a response parsed by the PHPBot class.
Details on the ParsedResponse object can be seen in Bot.php file.

Once you create these new methods, you'll want to register them. To do this, 
create an instance of your bot class, then call the register_response_method 
method for each of these methods. An example of this is given in start.php.

If you have any questions feel free to email me at kthprog@gmail.com.