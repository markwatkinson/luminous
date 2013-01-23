import groovy.sql.Sql

println "---- A working test of writing and then reading a blob into an Oracle DB ---"
sql = Sql.newInstance("jdbc:oracle:thin:@pignut:1521:TESTBNDY", "userName",
                     "paSSword", "oracle.jdbc.OracleDriver")

rowTest = sql.firstRow("select binarydata from media where mediaid = 11122345")
blobTest = (oracle.sql.BLOB)rowTest[0]

byte_stream_test = blobTest.getBinaryStream()
if( byte_stream_test == null ) {  println "Test: Received null stream!"  }

byte[] byte_array_test = new byte[10]
int bytes_read_test = byte_stream_test.read(byte_array_test)

print "Read $bytes_read_test bytes from the blob!"

sql.connection.close()