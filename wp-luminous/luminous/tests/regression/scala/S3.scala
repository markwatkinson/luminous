/*
 * Copyright (c) 2010, David Crawshaw <david@zentus.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.

 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */
package com.zentus.s3

private[s3] object HTTP {
  def date(date: java.util.Date): String = {
    val sdf = new java.text.SimpleDateFormat("EEE, d MMM yyyy HH:mm:ss '+0000'")
    sdf.setTimeZone(java.util.TimeZone.getTimeZone("UTC"))
    sdf.format(date)
  }

  def readAll(in: java.io.InputStream): Array[Byte] = {
    val out = new java.io.ByteArrayOutputStream()
    val buf = new Array[Byte](1024)
    var len = 0
    while (len >= 0) {
      out.write(buf, 0, len)
      len = in.read(buf, 0, buf.length)
    }
    out.toByteArray
  }

  def md5(bytes: Array[Byte]): String = {
    val md = java.security.MessageDigest.getInstance("MD5")
    md.update(bytes)
    new String(Base64.encode(md.digest))
  }

  def now(): String =
    date(new java.util.Date())
}

package acl {
abstract sealed trait ACL
final case object Private                 extends ACL {
  override def toString() = "private" }
final case object PublicRead              extends ACL {
  override def toString() = "public-read" }
final case object PublicReadWrite         extends ACL {
  override def toString() = "public-read-write" }
final case object AuthenticatedRead       extends ACL {
  override def toString() = "authenticated-read" }
final case object BucketOwnerRead         extends ACL {
  override def toString() = "bucket-owner-read" }
final case object BucketOwnerFullControl  extends ACL {
  override def toString() = "bucket-owner-full-control" }
}
import acl.ACL

class Item(
    val bucket: Bucket,
    val name: String,
    private var lastModified: Option[String],
    private var size: Option[Int]) {
  import java.io.InputStream

  def this(bucket: Bucket, name: String) = this(bucket, name, None, None)

  def get(): InputStream = {
    val conn = bucket.s3.getconn("GET", bucket.name, "/"+name)
    // TODO: set lastModified, size
    if (conn.getResponseCode != 200) {
      Console.println("exception: "+ conn.getResponseMessage)
      Console.println(
        io.Source.fromInputStream(conn.getErrorStream).getLines.mkString("\n")
      )
      // TODO: better error reporting, non-existant file, etc.
    }
    conn.getInputStream
  }

  def mkString(): String = {
    io.Source.fromInputStream(get).mkString
  }

  def set(in: String): Unit = set(in, "text/plain")
  def set(in: String, contentType: String): Unit = set(in.getBytes, contentType)
  def set(in: InputStream): Unit = set(in, "application/x-download")
  def set(in: InputStream, contentType: String): Unit =
    set(in, contentType, acl.Private)
  def set(in: InputStream, contentType: String, acl: ACL): Unit =
    set(HTTP.readAll(in), contentType, acl)
  def set(in: Array[Byte]): Unit = set(in, "application/x-download")
  def set(in: Array[Byte], contentType: String): Unit =
    set(in, contentType, acl.Private)

  def set(in: Array[Byte], contentType: String, acl: ACL): Unit = {
    val md5 = HTTP.md5(in)
    val conn = bucket.s3.getconn(
      "PUT", contentType, md5, bucket.name, "/"+name, "",
      "x-amz-acl:"+acl.toString+"\n")
    conn.setRequestProperty("Content-Length", in.length.toString)
    conn.setRequestProperty("Content-MD5", md5)
    conn.setRequestProperty("Content-Type", contentType)
    conn.setRequestProperty("x-amz-acl", acl.toString)
    conn.setFixedLengthStreamingMode(in.length)
    conn.setDoOutput(true)
    val out = conn.getOutputStream
    out.write(in, 0, in.length)
    out.flush
    if (conn.getResponseCode != 200) {
      bucket.s3.getxml(conn)
    }
    out.close
  }

  def delete() = {
    val conn = bucket.s3.getconn("DELETE", bucket.name, "/"+name)
    if (conn.getResponseCode != 200)
      bucket.s3.getxml(conn)
  }

  override def toString() = "Item("+name+")"
}

class Bucket(val s3: S3, val name: String) extends Iterable[Item] {
  import java.io.{ File, InputStream, FileInputStream }

  def apply(itemName: String) =
    new Item(this, itemName)

  def ++= (kvs: Iterable[(String,String)]): Unit =
    kvs map { case (k,v) => actors.Futures.future (+= (k,v)) } foreach { _() }

  def += (itemName: String, src: String) =
    new Item(this, itemName).set(src)
  def += (itemName: String, src: String, contentType: String) =
    new Item(this, itemName).set(src, contentType)
  def += (itemName: String, src: InputStream, contentType: String, acl: ACL) =
    new Item(this, itemName).set(src, contentType, acl)
  def += (itemName: String, src: File, contentType: String, acl: ACL) =
    new Item(this, itemName).set(new FileInputStream(src), contentType, acl)
  def -= (itemName: String) =
    new Item(this, itemName).delete

  override def elements(): Iterator[Item] =
    elements("")

  def elements(prefix: String): Iterator[Item] = {
    def genItem(contents: xml.Node): Item = {
      new Item(
        this,
        (contents \\ "Key").text,
        Some((contents \\ "LastModified").text),
        Some((contents \\ "Size").text.toInt)
      )
    }

    def sel(marker: Option[String]) = {
      val qs = "?prefix=" + prefix + marker.map("&marker="+_).getOrElse("")
      val conn = s3.getconn("GET", "", "", name, "/", qs, "")
      s3.getxml(conn)
    }

    new Iterator[Item]() {
      val ret = new collection.mutable.Queue[Item]()
      var res = sel(None)
      def genMarker() = (res \\ "Key").lastOption.map(_.text)
      def genItems()  = ((res \\ "Contents") map genItem)
      var marker = genMarker()
      ret ++= genItems()
      override def hasNext = {
        if (!ret.isEmpty) {
          true
        } else if (marker.isEmpty) {
          false
        } else {
          res = sel(marker)
          marker = genMarker()
          ret ++= genItems()
          !ret.isEmpty
        }
      }
      override def next = {
        if (!hasNext) {
          error("Iterator is done.")
        }
        ret.dequeue
      }
    }
  }
}

class S3(awsKeyId: String, awsSecretKey: String) extends Iterable[Bucket] {
  import java.net._
  import java.io.{ BufferedReader, InputStreamReader, OutputStreamWriter }
  import java.util.{ Date, Calendar, TimeZone }
  import javax.crypto.Mac
  import javax.crypto.spec.SecretKeySpec
  import xml._

  // RFC2104
  private def calcHMAC(data: String): String = {
    val encoding = "HmacSHA1"
    val key = new SecretKeySpec(awsSecretKey.getBytes, encoding)
    val mac = Mac.getInstance(encoding)
    mac.init(key)
    val rawHmac = mac.doFinal(data.getBytes)
    new String(Base64.encode(rawHmac))
  }

  private def authorization(
      verb: String, contentMD5: String, contentType: String, date: String,
      bucket: String, resource: String, amzHeaders: String) = {
    val toSign = (
      verb        + "\n" +
      contentMD5  + "\n" +
      contentType + "\n" +
      date        + "\n" +
      amzHeaders  +
      "/" + bucket + resource
    )
    //Console.println("signing: "+toSign)
    "AWS " + awsKeyId + ":" + calcHMAC(toSign)
  }

  private[zentus] def getconn(
      verb: String, bucket: String, resource: String): HttpURLConnection =
    getconn(verb, "", "", bucket, resource, "", "")

  private[zentus] def getconn(
      verb: String, contentType: String, contentMD5: String,
      bucket: String, resource: String, querystring: String,
      amzHeaders: String): HttpURLConnection = {
    val date = HTTP.now
    val url = "http://s3.amazonaws.com/" + bucket + resource + querystring
    val conn = new URL(url).openConnection.asInstanceOf[HttpURLConnection]
    val auth = authorization(
      verb, contentMD5, contentType, date, bucket, resource, amzHeaders)
    conn.setRequestMethod(verb)
    conn.setRequestProperty("Date", date)
    conn.setRequestProperty("Authorization", auth)
    conn
  }

  private[zentus] def getxml(conn: HttpURLConnection) = {
    try {
      val xml = XML.load(conn.getInputStream)
      //Console.println(new PrettyPrinter(80, 2).format(xml))
      xml
    } catch {
      case e =>
        Console.println("exception: "+ conn.getResponseMessage)
        Console.println(
          scala.io.Source.fromInputStream(
            conn.getErrorStream).getLines.mkString("\n")
        )
        throw e
    }
  }

  def +=(name: String) = {
    val conn = getconn("PUT", name.toLowerCase, "/")
    conn.setRequestProperty("Content-Length", "0")
    if (conn.getResponseCode != 200)
      error("Error creating bucket '"+name+"': "+conn.getResponseMessage)
  }

  def apply(name: String) = new Bucket(this, name)

  override def elements(): Iterator[Bucket] = {
    val date = HTTP.now

    val reqUrl = "http://s3.amazonaws.com"
    val conn = new URL(reqUrl).openConnection.asInstanceOf[HttpURLConnection]
    val auth = authorization("GET", "", "", date, "", "", "")
    conn.setRequestProperty("Date", date)
    conn.setRequestProperty("Authorization", auth)

    (getxml(conn) \\ "Name") map { n => new Bucket(this, n.text) } elements
  }
}