CRC32Stream Class:  'support/crc32_stream.php'
==============================================

This class calculates any CRC32 checksum in a stream-enabled class.  It is a direct port of the CubicleSoft C++ implementation of the same name.

A Cyclic Redundancy Check, or CRC, is used for detecting if an error exists somewhere in a data stream, but generally doesn't provide a means of correcting that error.  CRC32Stream is used by the DeflateStream class to validate incoming data for compression/decompression and can handle an unlimited amount of data due to the streaming capabilities (unlike the built-in and slightly buggy crc32 function in PHP).

Example usage:

```php
<?php
	require_once "support/crc32_stream.php";

	$data = "CRC twice the data";
	$data2 = " for twice the fun!";
	$crc = new CRC32Stream();
	$crc->Init();
	$crc->AddData($data);
	$crc->AddData($data2);
	$result = $crc->Finalize();

	echo sprintf("%u\n", $result);
?>
```

CRC32Stream::Init($options = false)
-----------------------------------

Access:  public

Parameters:

* $options - An optional array of information or a boolean of false (Default = false).

Returns:  Nothing.

This function initializes the class.  The class can be reused by calling Init().  The $options array must have key-value pairs as follows:

* poly - An integer for the polynomial.
* start - An integer for the starting value.
* xor - An integer for the XOR value.
* refdata - A boolean that indicates whether or not to reflect the data.
* refcrc - A boolean that indicates whether or not to reflect the final CRC value.

The most popular and widely used CRC32 values are used if false is passed in as the value for $options, which is the default.  In general, the default is probably what you want to use.

CRC32Stream::AddData($data)
---------------------------

Access:  public

Parameters:

* $data - A string containing the data to process for the CRC.

Returns:  A boolean of true if Finalize() has not been called, false otherwise.

This function processes the data and updates the internal CRC state.

CRC32Stream::Finalize()
-----------------------

Access:  public

Parameters:  None.

Returns:  An integer containing the final CRC value.

This function finalizes the CRC state and return the value.  To use the class again after calling this function, call Init().

CRC32Stream::SHR32($num, $bits)
-------------------------------

Access:  private

Parameters:

* $num - A 32-bit integer to shift right.
* $bits - The number of bits to shift.

Returns:  A valid 32-bit integer shifted right the specified number of bits.

This internal function shifts an integer to the right the specified number of bits across all hardware that PHP runs on.

CRC32Stream::SHL32($num, $bits)
-------------------------------

Access:  private

Parameters:

* $num - A 32-bit integer to shift left.
* $bits - The number of bits to shift.

Returns:  A valid 32-bit integer shifted right the specified number of bits.

This internal function shifts an integer to the left the specified number of bits across all hardware that PHP runs on.

CRC32Stream::LIM32($num)
------------------------

Access:  private

Parameters:

* $num - A 32-bit integer to cap to 32-bits.

Returns:  A valid 32-bit integer.

This internal function is used by other functions in the class to cap 32-bit integers on all hardware that PHP runs on.
