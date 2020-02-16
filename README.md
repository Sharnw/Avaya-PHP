## Avaya-PHP: Integration for SOAP/Socket interactions

This library is for interaction with an Avaya phone systems server using PHP.

I built it myself while working at Tow.com.au as we had an Avaya phone system but devconnect did not provide any PHP packages or code examples.

Our CEO gave me approval to release it on github, and a couple of years later when Tow.com.au ceased operations and their github account went down i requested permission to release the repository myself. Unfortunately a lot of the documentation was lost but i hope this helps the next poor dev who needs to write an avaya connector in PHP.

It comes with both a SoapWrapper and a SocketConnector to interact with your in-house Avaya server. The socket connector opens up a lot more functionality including the ability to manually dial digits on a specific device (which we used to enter our gate-opening code).

### Relevant Avaya Docs:

* https://downloads.avaya.com/css/P8/documents/100119843
