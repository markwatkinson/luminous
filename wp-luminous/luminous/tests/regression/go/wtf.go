// wtf - web server for TweetFreq

package main

import (
        "json"
        "time"
        "strconv"
        "http"
        "io/ioutil"
        "io"
        "fmt"
        "strings"
        "os"
)


type JTweets struct {
        Results []Result
}

type Result struct {
        Profile_image_url string
        Created_at        string
        From_user         string
}

type JUsers struct {
        Users []User
}

type User struct {
        Screen_name string
}

const canvasWidth = 1000 // 960
const canvasHeight = 750 // 750
const lmargin = 50
const tmargin = 95
const rmargin = canvasWidth - lmargin
const tloc = 40
const daybegin = "T00:00:00Z"
const dayend = "T23:59:59Z"
const secondsPerDay = 24 * 60 * 60
const maxDays = 4
const listURIfmt = "http://%s@api.twitter.com/1/%s/%s/members.json"
const queryURI = "http://search.twitter.com/search.%s?q=%s+since:%s+until:%s&rpp=%d"
const defaultPic = "http://static.twitter.com/images/default_profile_normal.png"
const linefill = "rgba(128,128,128,.20)"
const markerfill = "rgba(127,0,0,.40)"
const textfill = "rgb(127,127,127)"
const canvasfill = "rgb(255,255,255)"
const fontname = "Calibri,Lucida,sans-serif"
const initfmt = `<html><head><title>%s</title><script type="application/javascript">
function draw() {
var canvas = document.getElementById("canvas");
if (canvas.getContext) {
var C = canvas.getContext("2d");var p2=Math.PI*2;
C.fillStyle="%s";C.fillRect(0,0,%d,%d);
C.font="32pt %s";C.textAlign="center";C.fillStyle="%s";C.fillText("%s",%d,%d);C.font="10pt %s";
`
const legendfmt = `C.beginPath();C.moveTo(%d,%d);C.lineTo(%d,%d);C.lineTo(%d,%d);C.closePath();C.fill();C.fillStyle="%s";C.fillText("%s",%d,%d);
`
const endfmt = `}}</script></head><body onload="draw();"><canvas id="canvas" width="%d" height="%d"></canvas></body></html>
`
const userfmt = `
var im%d=new Image();im%d.onload=function doim%d(){C.drawImage(im%d,%d,%d,%d,%d);}
im%d.src="%s";
C.fillStyle="%s";C.fillText("%s [%d]",%d,%d);C.fillStyle="%s";C.fillRect(%d,%d,%d,%d);C.fillStyle="%s";
`
const pubfmt = `C.beginPath();C.arc(%d,%d,%d,0,p2,true);C.fill();
`
const setfont = `C.textAlign="left";C.font="16pt ` + fontname + `";`

var upass = "username:password"
var initbegin = "2009-12-01"
var initend = "2009-12-01"
var title = "Twitter Update Frequency"
var begindate = "2009-12-01"
var enddate = "2009-12-01"
var qformat = "json"
var tcount = 50
var spacing = 60
var picwidth int64 = 48
var markerwidth int64 = 16
var lineheight int64 = 24

var monthsOfYear = map[string]int{
        "Jan": 1, "Feb": 2, "Mar": 3, "Apr": 4, "May": 5, "Jun": 6,
        "Jul": 7, "Aug": 8, "Sep": 9, "Oct": 10, "Nov": 11, "Dec": 12,
}

func vmap(value int64, low1 int64, high1 int64, low2 int64, high2 int64) int64 {
        return low2 + (high2-low2)*(value-low1)/(high1-low1)
}

func secbetween(b string, e string) int64 { return isosec(e) - isosec(b) }

func isosec(s string) int64 { return isototime(s).Seconds() }

func isototime(s string) *time.Time {
        if len(s) != 20 {
                return nil
        }
        var year, _ = strconv.Atoi64(s[0:4])
        var month, _ = strconv.Atoi(s[5:7])
        var day, _ = strconv.Atoi(s[8:10])
        var hour, _ = strconv.Atoi(s[11:13])
        var minute, _ = strconv.Atoi(s[14:16])
        var second, _ = strconv.Atoi(s[17:19])

        t := time.Time{Year: year, Month: month, Day: day,
                Hour: hour, Minute: minute, Second: second,
                Zone: "UTC",
        }
        return &t
}


func rfc1123sec(s string) int64 { return rfc1123totime(s).Seconds() }

func rfc1123totime(s string) *time.Time {
        if len(s) != 31 {
                return nil
        }
        var year, _ = strconv.Atoi64(s[12:16])
        var month, _ = monthsOfYear[s[8:11]]
        var day, _ = strconv.Atoi(s[5:7])
        var hour, _ = strconv.Atoi(s[17:19])
        var minute, _ = strconv.Atoi(s[20:22])
        var second, _ = strconv.Atoi(s[23:25])

        t := time.Time{Year: year, Month: month, Day: day,
                Hour: hour, Minute: minute, Second: second,
                Zone: "UTC",
        }
        return &t
}


func isodatestring(t *time.Time) string {
        return fmt.Sprintf("%04d-%02d-%02d", t.Year, t.Month, t.Day)
}

func initialcode(c *http.Conn, t string, b string, e string) {
        io.WriteString(c, fmt.Sprintf(initfmt, t, canvasfill, canvasWidth,
                canvasHeight, fontname, textfill, t, canvasWidth/2, tloc, fontname))
        io.WriteString(c, legend(b+daybegin, e+dayend, tmargin, 10, 10))
}

func legend(b string, e string, y int, w int, h int) string {
        var x int64

        days := int(secbetween(b, e)/secondsPerDay) + 2
        var w2 int64
        var pl = picwidth + lmargin
        var ds string

        w2 = int64(w / 2)
        yh := y - h
        ib := isosec(b)
        ie := isosec(e)
        s := ""
        lx := ib
        for i := 0; i < days; i++ {
                x = vmap(lx, ib, ie, pl, rmargin)
                ds = isodatestring(time.SecondsToUTC(lx))
                s = s + fmt.Sprintf(legendfmt, x, y, x-w2, yh, x+w2, yh,
                        textfill, ds[0:10], x, yh-3)
                lx += secondsPerDay
        }
        s += setfont
        return s
}

func readjson(c *http.Conn, r io.ReadCloser, b string, e string, yv int) int {
        var twitter JTweets
        var data []byte
        var ntweets int
        data, err := ioutil.ReadAll(r)

        b += daybegin
        e += dayend
        var y = int64(yv)

        if err == nil {
                ok, errtok := json.Unmarshal(string(data), &twitter)
                if ok {
                        ntweets = len(twitter.Results)
                        if ntweets > 0 {
                                var pl = picwidth + lmargin
                                io.WriteString(c, fmt.Sprintf(userfmt, y, y, y, y, lmargin, y,
                                        picwidth, picwidth, y, twitter.Results[0].Profile_image_url, textfill,
                                        twitter.Results[0].From_user, ntweets, pl+5, y+picwidth, linefill,
                                        pl, y, rmargin-pl, lineheight, markerfill))

                                for i := 0; i < ntweets; i++ {
                                        io.WriteString(c, fmt.Sprintf(pubfmt,
                                                vmap(rfc1123sec(twitter.Results[i].Created_at),
                                                        isosec(b), isosec(e), pl, rmargin),
                                                y+(lineheight/2), markerwidth/2))
                                }
                                return ntweets
                        }
                } else {
                        fmt.Printf("Unable to parse the JSON : [%v]\n", errtok)
                }
        }
        return ntweets
}


func finalcode(c *http.Conn) {
        io.WriteString(c, fmt.Sprintf(endfmt, canvasWidth, canvasHeight))
}

func tf(c *http.Conn, b string, e string, n int, y int, s string) int {

        var qs string
        var ntf int = 0

        if len(s) < 1 {
                return ntf
        }

        if s[0] == '#' && len(s) > 1 {
                qs = s
        } else {
                qs = "from:" + s
        }
        r, _, err := http.Get(fmt.Sprintf(queryURI,
                qformat, http.URLEscape(qs), b, e, n))
        if err == nil {
                if r.StatusCode == http.StatusOK {
                        ntf = readjson(c, r.Body, b, e, y)
                } else {
                        fmt.Printf("Twitter is unable to search for %s (%s)\n", s, r.Status)
                }
                r.Body.Close()
        } else {
                fmt.Printf("%v\n", err)
        }
        return ntf
}

func initparams() {
        tcount = 50
        lineheight = 24
        picwidth = 48
        spacing = 60
        markerwidth = 16
        title = "Twitter Update Frequency"
        begindate = initbegin
        enddate = initend
}

func tfquery(req *http.Request) {
        query := strings.Split(req.URL.RawQuery, "&", 0)

        //fmt.Printf("path : %v\n", path)
        //fmt.Printf("query: %v\n", query)

        for i := 0; i < len(query); i++ {
                nv := strings.Split(query[i], "=", 2)
                if len(nv) == 2 {
                        switch nv[0] {
                        case "b":
                                begindate = nv[1]
                        case "e":
                                enddate = nv[1]
                        case "t":
                                title, _ = http.URLUnescape(nv[1])
                        case "c":
                                tcount, _ = strconv.Atoi(nv[1])
                        case "l":
                                lineheight, _ = strconv.Atoi64(nv[1])
                        case "p":
                                picwidth, _ = strconv.Atoi64(nv[1])
                        case "s":
                                spacing, _ = strconv.Atoi(nv[1])
                        case "m":
                                markerwidth, _ = strconv.Atoi64(nv[1])
                        }
                }
                //fmt.Printf("nv: %v\n", nv)
                //showparams("Using  ")
        }
}


func tfusers(c *http.Conn, req *http.Request) {

        initparams()
        tfquery(req)
        path := strings.Split(req.URL.Path, "/", 0)
        fmt.Printf("path: %v\n", path)

        if len(path) > 1 {
                users := strings.Split(path[2], ",", 0)
                fmt.Printf("%s %v\n", c.RemoteAddr, users)
                tfdisplay(c, users)
        } else {
                fmt.Printf("bogus path: %v\n", path)
        }

}

func tflist(c *http.Conn, req *http.Request) {
        var twitter JUsers

        initparams()
        tfquery(req)
        path := strings.Split(req.URL.Path, "/", 0)
        fmt.Printf("path: %v\n", path)
        if len(path) > 3 {
                r, _, err := http.Get(fmt.Sprintf(listURIfmt, upass, path[2], path[3]))
                if err == nil {
                        data, _ := ioutil.ReadAll(r.Body)
                        ok, _ := json.Unmarshal(string(data), &twitter)
                        if ok {
                                nu := len(twitter.Users)
                                users := make([]string, nu)
                                for i := 0; i < nu; i++ {
                                        users[i] = twitter.Users[i].Screen_name
                                }
                                fmt.Printf("members: %v\n", users)
                                tfdisplay(c, users)
                        }
                }
        }
}


func tfdisplay(c *http.Conn, users []string) {
        initialcode(c, title, begindate, enddate)
        for i, y := 0, tmargin; i < len(users); i++ {
                if tf(c, begindate, enddate, tcount, y, users[i]) > 0 {
                        y += spacing
                }
        }
        finalcode(c)
}

func showparams(why string) {
        fmt.Printf("%s: t=\"%s\" b=%s e=%s c=%d l=%d p=%d s=%d m=%d f=%s\n",
                why, title, begindate, enddate, tcount,
                lineheight, picwidth, spacing, markerwidth, qformat)
}

func main() {
  if len(os.Args) > 1 {
    upass = os.Args[1]
  }
        t := time.UTC()
        initend = isodatestring(t)
        initbegin = isodatestring(time.SecondsToUTC(t.Seconds() -
                (secondsPerDay * maxDays)))
        showparams("init")
        http.Handle("/users/", http.HandlerFunc(tfusers))
        http.Handle("/list/", http.HandlerFunc(tflist))
        err := http.ListenAndServe(":1958", nil)
        if err != nil {
                panic("ListenAndServe: ", err.String())
        }
}