DeflateStream Class:  'support/deflate_stream.php'
==================================================

This class implements three compression/extraction algorithms in wide use on web servers in a stream-enabled format:  RFC1951 (raw deflate - default), RFC1950 (ZLIB), and RFC1952 (gzip).

This class works entirely using RAM.  Most of the compression routines in PHP require using an external temporary file to compress and uncompress data.  On the downside, the RAM only aspect of this class does make PHP more suceptible to significant issues such as ZIP bombs.

The PHP functions stream_filter_append(), stream_filter_remove(), and gzcompress() are required to exist for this class to work.  Any modern version of PHP compiled with zlib support should work.  The CRC32Stream class is also required for gzip streams.

Example usage:

```php
<?php
	require_once "support/deflate_stream.php";

	if (!DeflateStream::IsSupported())
	{
		echo "Please enable the gzip module or compile it into PHP.\n";

		exit();
	}

	$data = "Compress me with a digital hug!";

	// Static compress/uncompress.
	$data = DeflateStream::Compress($data);
	$data = DeflateStream::Uncompress($data);
	echo $data . "\n";

	// Streaming compress/uncompress.
	// NOTE:  No error checking is performed here.  You should probably do that.
	$ds = new DeflateStream();
	$ds->Init("wb");
	$ds->Write($data);
	$ds->Finalize();
	$data = $ds->Read();

	$ds->Init("rb", -1, array("type" => "auto"));
	$ds->Write($data);
	$ds->Finalize();
	$data = $ds->Read();
	echo $data . "\n";
?>
```

DeflateStream::IsSupported()
----------------------------

Access:  public static

Parameters:  None.

Returns:  A boolean of true if DeflateStream is supported, false otherwise.

This static function should be used to determine if DeflateStream will work before attempting to use the class.

DeflateStream::Compress($data, $compresslevel = -1, $options = array())
-----------------------------------------------------------------------

Access:  public static

Parameters:

* $data - The string to compress.
* $compresslevel - An integer representing the compression level to use (Default is -1).
* $options - An array of options to use for the Init() call (Default is array()).

Returns:  A string containing the compressed data on success, a boolean of false otherwise.

This static function compresses a string completely in memory at once.  See Init() for a list of valid options for the $options array.

DeflateStream::Uncompress($data, $options = array("type" => "auto"))
--------------------------------------------------------------------

Access:  public static

Parameters:

* $data - The string to compress.
* $options - An array of options to use for the Init() call (Default is array("type" => "auto")).

Returns:  A string containing the compressed data on success, a boolean of false otherwise.

This static function uncompresses a string completely in memory at once.  See Init() for a list of valid options for the $options array.

DeflateStream::Init($mode, $compresslevel = -1, $options = array())
-------------------------------------------------------------------

Access:  public

Parameters:

* $mode - A string of either "wb" to compress or "rb" to uncompress.
* $compresslevel - An integer representing the compression level to use (Default is -1).
* $options - An array of options (Default is array()).

Returns:  A boolean of true if initialization was successful, false otherwise.

This function initializes the class to either compress or uncompress written data.  The $compresslevel is dependent upon the algorithm but -1 (the default) usually strikes a good balance between performance and size when compressing data.  The $options array can currently accept the following key-value pair:

* type - A string of one of "rfc1951", "raw", "rfc1950", "zlib", "rfc1952", "gzip", and "auto" (Default is "raw").  The "auto" option is only valid when uncompressing data.

DeflateStream::Write($data)
---------------------------

Access:  public

Parameters:

* $data - The string to process.

Returns:  A boolean of true if the data was processed successfully, false otherwise.

This function processes incoming data and moves any available compressed/uncompressed results into the output area for reading.  For performance reasons, as much data as possible should be sent to Write() at one time.  Depending on the mode, not all data may be sent at once.  Note that just because data goes in doesn't mean it will result in any output when Read() is called.

DeflateStream::Read()
---------------------

Access:  public

Parameters:  None.

Returns:  A string containing any available output.

This function returns the available output from the previous Write() call and also clears the internal output.

DeflateStream::Finalize()
-------------------------

Access:  public

Parameters:  None.

Returns:  A boolean of true if finalization succeeded, false otherwise.

This function flushes all internal data streams and finalizes the output for the last Read() call.  After Finalize() is called, no more data may be written but there will likely be data to be read.

DeflateStream::ProcessOutput()
------------------------------

Access:  private

Parameters:  None.

Returns:  Nothing.

This internal function extracts the data from the output end of things and stores it for a future Read() call.  The function exists mostly because PHP is broken when working with ftell() and PHP filters.  See:  https://bugs.php.net/bug.php?id=49874

DeflateStream::ProcessInput($final = false)
-------------------------------------------

Access:  private

Parameters:

* $final - A boolean that indicates whether or not this is the last input (Default is false).

Returns:  Nothing.

This internal function processes data made available as a result of calling the Write() and Finalize() functions.

DeflateStream::SHL32($num, $bits)
---------------------------------

Access:  private

Parameters:

* $num - A 32-bit integer to shift left.
* $bits - The number of bits to shift.

Returns:  A valid 32-bit integer shifted right the specified number of bits.

This internal function shifts an integer to the left the specified number of bits across all hardware that PHP runs on.

DeflateStream::LIM32($num)
--------------------------

Access:  private

Parameters:

* $num - A 32-bit integer to cap to 32-bits.

Returns:  A valid 32-bit integer.

This internal function is used by other functions in the class to cap 32-bit integers on all hardware that PHP runs on.

DeflateStream::ADD32($num, $num2)
---------------------------------

Access:  private

Parameters:

* $num - A 32-bit integer.
* $num2 - Another 32-bit integer.

Returns:  The 32-bit capped result of adding two 32-bit integers together.

This internal function adds two 32-bit integers together on all hardware that PHP runs on.
