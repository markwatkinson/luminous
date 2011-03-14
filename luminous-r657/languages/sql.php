<?php
class LuminousGrammarSQL extends LuminousGrammar
{
  
  public $keyword_regex =  "/(?<![a-zA-Z0-9_$])(?:%KEYWORD)(?![a-zA-Z0-9_])/i";
  
  public $keywords = array(
    'ABORT|ACTION|ADD|AFTER|ALL|ALTER|ANALYZE|AND|AS|ASC|ATTACH|AUTO(?:_)?INCREMENT',
    'BEFORE|BEGIN|BETWEEN|BY',
    'CASCADE|CASE|CAST|CHECK|COLLATE|COLUMN|COMMIT|CONFLICT|CONSTRAINT|CREATE|CROSS|CURRENT_(?:DATE|TIME(?:STAMP)?)',
    'DATABASE|DEFAULT|DEFERRABLE|DEFERRED|DELETE|DESC|DETACH|DISTINCT|DROP',
    'EACH|ELSE|END|ESCAPE|EXCEPT|EXCLUSIVE|EXISTS|EXPLAIN',
    'FAIL|FOR|FOREIGN|FROM|FULL',
    'GLOB|GROUP',
    'HAVING',
    'IF|IGNORE|IMMEDIATE|IN|INDEX|INDEXED|INITITIALLY|INNER|INSERT|INSTEAD|INTERSECT|INTO|IS|ISNULL',
    'JOIN',
    'KEY',
    'LEFT|LIKE|LIMIT',
    'MATCH',
    'NATURAL|NO|NOT|NOTNULL|NULL',
    'OF|OFFSET|ON|OR|ORDER|OUTER',
    'PLAN|PRAGMA|PRIMARY',
    'QUERY',
    'RAISE|REFERENCES|REGEXP|REINDEX|RELEASE|RENAME|REPLACE|RESTRICT|RIGHT|ROLLBACK|ROW',
    'SAVEPOINT|SELECT|SET',
    'TABLE|TEMP|TEMPORARY|THEN|TO|TRANSACTION|TRIGGER',
    'UNION|UNIQUE|UPDATE|USING',
    'VACUUM|VALUES|VIEW|VIRTUAL',
    'WHEN|WHERE');
  
  public $type_regex =  "/(?<![a-zA-Z0-9_$])(:?%TYPE)(?![a-zA-Z0-9_])/i";
  
  public $types = array(
    'BLOB|BIGINT(?:EGER)?',
    'CHAR',
    'DATE(?:TIME)?|DECIMAL|DOUBLE',
    'FLOAT',
    'INT(?:EGER)?',
    'MEDIUMINT(?:EGER)?',
    'SMALLINT(?:EGER)?',
    'TIME(?:STAMP)?|TINYINT(?:EGER)?',
    'VARCHAR');
  
  
  
  public function __construct()
  {
    
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('sql');
    $this->SetInfoVersion('r657');        
    
    
    $this->delimited_types = array(
      
      new LuminousDelimiterRule(0, 'COMMENT', 0, '/*', '*/'),      
      
      luminous_generic_comment_sl('--'),
      luminous_generic_comment_sl('#'), // MYSQL
      
      luminous_generic_sql_string("'", 
        'luminous_type_callback_sql_single_quotes',
        true),
      new LuminousDelimiterRule(0, "STRING", 0, "`", "`",
        'luminous_type_callback_generic_string'),
      new LuminousDelimiterRule(0, "STRING", 0, '"', '"',
        'luminous_type_callback_generic_string')
      );
      
    $this->SetSimpleTypeRules();
    
    
  }

}
