<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Login Alert</title>
</head>

<body>
    Their was a new login activity on your account.
    
    <div>Device : {{$deviceType}} </div>
    @if($deviceNameIsKnown)
    <div>Device : {{$deviceName}} </div>
    @endif

    @if($locationIsUnKnown)

    @if($countryisKnown)
    <div>country : {{$country}} </div>
    @endif

    @if($cityisKnown)
    <div>city : {{$city}} </div>
    @endif

    @endif
</body>

</html>