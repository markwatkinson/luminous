package free.cafekiwi.gotapi;
import groovy.xml.MarkupBuilder
/**
* @author Marc DEXET
**/
class XmlGotApiGenerator {


        static void main(args) {
          def writer = new FileWriter( new File('gotapi.xml'))
          def xml = new MarkupBuilder(writer)
          def pages = []

          def apidocRoot = 'http://groovy.codehaus.org/api'
          def parser = new JavaDocParser();

          def methodNameFilter  = {
                        anchor ->
                        def name = anchor.'@name'
                        name != null && name.indexOf('(') >=0
          }


          def packageFilter     = {
                          anchor ->
                          def url = anchor.'@href'
                          url != null  && url.endsWith( "/package-frame.html")
          }

          def classFilter =  {
                          anchor ->
                          def url = anchor.'@href'
                          url != null && ! url.contains ('/')
          }

          def parseAndGet = {
                          url, filter ->
                          parser.parse( url ).depthFirst().A.findAll( filter )
          }

         parseAndGet("$apidocRoot/overview-frame.html", packageFilter).each {

                  def packBaseURL = it.'@href' - "/package-frame.html"
                  def packPage = new Page(      title: packBaseURL.replace('/', '.') ,
                                                                        type: 'package',
                                                                        url: "${apidocRoot}/${it.'@href'}"
                                                                        );

                  pages << packPage
                  println "PackPAGE ${packPage}"

                  parseAndGet( packPage.url, classFilter).each {

                          def url = it.'@href'
                          def fullUrl = "${apidocRoot}/${packBaseURL}/${url}"
                          def fqn = url.replace('/', '.')- ".html"
                          def parts = fqn.tokenize('.')

                          def classPage = new Page( title: fqn ,
                                                                                type: 'class',
                                                                                url: fullUrl
                                                                                );

                          packPage.children << classPage

                          println "Class PAGE: ${classPage}"

                          parseAndGet( classPage.url, methodNameFilter ).each {
                                  def methodeName = it.'@name'
                                  def methodPage =  new Page(   title: methodeName ,
                                                                                                type: 'method',
                                                                                                url: classPage.url+'#'+methodeName);
                                  classPage.children << methodPage
                                  println "Method PAGE: ${methodPage}"
                          }
                  }
          }
        xml.pages() {
                pages.each{
                        packIt ->
                        xml.page(title: packIt.title , type: packIt.type, url: packIt.url) {
                                packIt.children.each {
                                        classIt ->
                                        xml.page(title: classIt.title , type: classIt.type, url: classIt.url) {
                                                classIt.children.each {
                                                        methodIt ->
                                                        xml.page(title: methodIt.title , type: methodIt.type, url: methodIt.url)
                                                }
                                        }
                                }
                        }
                }
        }
        println 'END'
        }

}

class Page {
        def title
        def type
        def url
        def children = []
        public String toString() {
                "Titre: ${title} Type: ${type} URL:${url} \\n\\t ${children.collect{it.toString()+'\\n\\t' }}"
        }
}

class JavaDocParser {
        def parser
        /* Constructor */
        JavaDocParser() {
          def nekoparser = new org.cyberneko.html.parsers.SAXParser()
          nekoparser.setFeature('http://xml.org/sax/features/namespaces', false)
          parser = new XmlParser(nekoparser);
        }

        def parse(url) {
                return parser.parse(url)
        }
} 
