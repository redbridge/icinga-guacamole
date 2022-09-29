This sample page will allow you to redirect your clients to your Guacamole server that allows for adhoc-conections. Authentication is performed by Guacamole.

The javascript code embedded in the page will talk to the Guacamole API and create an ad-hoc connection in the background before redirecting to the client
connection profile.

It must be located on the Guacamole server as it must be able make authenticated requests towards the API. The user must be authenticated before they can be redirected.

URLs are expected to be in this format `redirect.html#rdp:myhost:3389` 
  where `myhost` is the host address of the connection,
  and `rdp` is the protocol of the connection,
  and `3389` is the port number used for the connection.
