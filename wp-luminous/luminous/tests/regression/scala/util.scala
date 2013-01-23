package libscala

object util {
    //looping functions:

    def repeat(iterations: Int)(func: => Unit) = {
        var i = 0; while (i < iterations) { func; i += 1 }
    }

    def simpleLoop(iterations: Int)(func: (Int) => Unit) = {
        var i = 0; while (i < iterations) { func(i); i += 1 }
    }

    def rangeLoop(begin: Int, end: Int, step: Int)(function: (Int)=>Unit) = {
        var i = begin;
        if (step >= 0) while (i < end) {
            function(i)
            i += step
        } else while (i > end) {
            function(i)
            i += step
        }
    }

    def arrayLoop[T](array: Array[T])(function: (Int, T)=>Unit) = {
        val l = array.size
        var i = 0; while(i < l) {
            function(i, array(i))
            i += 1
        }
    }

    def seqLoop[T](seq: Seq[T])(function: (Int, T) => Unit) = {
        val l = seq.size
        var i = 0; while(i < l) {
            function(i, seq(i))
            i += 1
        }
    }

    def spawn(func: => Unit) = {
        val thread = new Thread {
            final override def run = {func}
            setDaemon(true)
        }
        thread.start()
    }

    def thread(func: ()=> Unit): Unit = spawn{func()}

    def connString(db: String, host:String, port:Int, dbname:String, user:String, pass:String) = {
        "jdbc:%s://%s:%s/%s?user=%s&password=%s".format(db, host, port, dbname, user, pass)
    }

    /** benchmark a code block e.g. util.bench{statement; statement; statement} */
    def bench(block: => Any) = {
        val begin = System.currentTimeMillis()
        println("Answer: " + block)
        val end = System.currentTimeMillis()
        println("Time Taken: " + (end - begin)/1000.0 + " seconds.")
    }

    def unixTime = System.currentTimeMillis/1000.0

    def foreach[T](iterable: java.lang.Iterable[T])(function: (T) => Unit) = {
        val iterator = iterable.iterator
        while(iterator.hasNext) function(iterator.next)
    }

    def fetchUrlBuf(url: String): StringBuffer = {
        val urlObject = new java.net.URL(url);
        val input = new java.io.BufferedInputStream(urlObject.openStream)
        val buffer = new StringBuffer

        var ptr = input.read
        while (ptr != -1) {
            buffer.append(ptr.asInstanceOf[Char])
            ptr = input.read
        }
        buffer
    }

    def fetchUrl(url: String): String = fetchUrlBuf(url).toString

    def randomBytes(size: Int) = {
        val random = new java.util.Random
        val buf = new Array[Byte](size)
        random.nextBytes(buf)
        buf
    }

    /** String consisting of only the characters in safeChars */
    def randomSafeString(size: Int, safeChars: String) = {
        val sb = new StringBuilder
        val l = safeChars.length
        for (b <- randomBytes(size)) sb.append (safeChars(b % l))
        sb.toString
    }

    /** Characters that can be returned in a url-safe string */
    val safeChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890._"

    /** URL-safe String */
    def randomSafestring(size:Int) = randomSafeString(size, safeChars)

    /** For templating. Concatenates all arguments into a String, which it returns */
    def joinSeq(sep: String, items: Seq[Any]): String = {
        val builder = new StringBuilder()
        var l = items.length
        var i = 0; while(i < l-1) {
            builder.append(items(i))
            builder.append(sep)
            i += 1
        }
        builder.append(items(l - 1))
        builder.toString()
    }

    def join(sep: String, items: Any*): String = joinSeq(sep, items)
}