{- Parsec parser for fullerror.  The sole expected method, parseFullError,
   takes a string as input, and returns a list of terms, where each term
   was separated by a semicolon in the input.
 -}
module Parser ( parseFullError ) where

import Text.ParserCombinators.Parsec
import qualified Text.ParserCombinators.Parsec.Token as P
import Text.ParserCombinators.Parsec.Language
import Control.Monad
import Control.Monad.Error
import Control.Monad.State
import Data.Char

import Syntax
import Typing
import TaplError
import SimpleContext

{- ------------------------------
   Lexer, making use of the Parsec.Token and Language
   modules for ease of lexing programming language constructs
   ------------------------------ -}
fullErrorDef = LanguageDef
                { commentStart    = "/*"
                , commentEnd      = "*/"
                , commentLine     = ""
                , nestedComments  = False
                , identStart      = letter 
                , identLetter     = letter <|> digit
                , opStart         = fail "no operators"
                , opLetter        = fail "no operators"
                , reservedOpNames = []
                , caseSensitive   = True
                , reservedNames   = ["inert", "true", "false", "if", "then", "else", "Bool", "Nat", "String", "Unit", "Float", "case", "of", "as", "lambda", "let", "in", "fix", "letrec", "timesfloat", "succ", "pred", "iszero", "unit", "try", "with", "error", "Bot"]
                }

lexer = P.makeTokenParser fullErrorDef

parens        = P.parens        lexer
braces        = P.braces        lexer
squares       = P.squares       lexer
identifier    = P.identifier    lexer
reserved      = P.reserved      lexer
symbol        = P.symbol        lexer
whiteSpace    = P.whiteSpace    lexer
float         = P.float         lexer
semi          = P.semi          lexer
comma         = P.comma         lexer
colon         = P.colon         lexer
stringLiteral = P.stringLiteral lexer
natural       = P.natural       lexer

{- ------------------------------
   Parsing Binders
   ------------------------------ -}

-- due to the definition of "identState" in fullErrorDef,
-- this is the only way that an underscore can enter our system,
-- and thus there is no chance of it being misused as a variable elsewhere
parseVarBind = do var <- identifier <|> symbol "_"
                  symbol ":"
                  ty <- parseType
                  let binding = VarBind ty
                  updateState $ appendBinding var binding
                  return $ TmBind var binding


parseAbbBind forLetrec
    = do var <- identifier <|> symbol "_"
         -- For a letrec, we need to temporarily add a binding, so that
         -- we can lookup this variable while parsing the body.  
         -- Note that both setState calls use the original Context
         ctx <- getState
         when forLetrec $ setState $ appendBinding var NameBind ctx
         binding <- getBinding var
         setState $ appendBinding var binding ctx
         return $ TmBind var binding
    where getBinding var = if (isUpper $ var !! 0)
                           then (try $ completeTyAbbBind var) <|>
                                (return TyVarBind)
                           else withType <|> withoutType
          withoutType    = do symbol "="
                              t <- parseTerm
                              liftM (TmAbbBind t) (getType t)
          withType       = do symbol ":"
                              ty <- parseType
                              symbol "="
                              liftM ((flip TmAbbBind) (Just ty)) parseTerm
          completeTyAbbBind var 
                         = do symbol "="
                              ty <- parseType
                              return $ TyAbbBind ty
          getType t      = do ctx <- getState
                              case evalState (runErrorT (typeof t)) ctx of
                                Left err -> return Nothing
                                Right ty -> return $ Just ty

parseBinder = (try parseVarBind) <|> (parseAbbBind False)

{- ------------------------------
   Parsing Types
   ------------------------------ -}

parseTypeBool   = reserved "Bool"   >> return TyBool

parseTypeNat    = reserved "Nat"    >> return TyNat

parseTypeFloat  = reserved "Float"  >> return TyFloat

parseTypeUnit   = reserved "Unit"   >> return TyUnit

parseTypeString = reserved "String" >> return TyString

parseTypeBot    = reserved "Bot"    >> return TyBot

parseNamedType  = do ty <- identifier
                     if isUpper $ ty !! 0
                       then makeNamedType ty
                       else fail "types must start with an uppercase letter"
    where makeNamedType ty       = do ctx <- getState 
                                      throwsToParser $ makeTyVarOrTyId ty ctx
          makeTyVarOrTyId ty ctx = catchError (makeTyVar ty ctx) 
                                   (\e -> return $ TyId ty)
          makeTyVar       ty ctx = do idx <- indexOf ty ctx
                                      return $ TyVar $ TmVar idx (ctxLength ctx)

parseVariantType = do symbol "<"
                      fields <- sepBy1 parseField comma
                      symbol ">"
                      return $ TyVariant fields
    where parseField = do var <- identifier
                          colon
                          ty <- parseType
                          return (var, ty)

parseTypeArr = parseTypeBool   <|>
               parseTypeNat    <|>
               parseTypeFloat  <|>
               parseTypeUnit   <|>
               parseTypeString <|>
               parseTypeBot    <|>
               parseNamedType  <|>
               parseVariantType  <|>
               braces parseType

parseType = parseTypeArr `chainr1` (symbol "->" >> return TyArr)

{- ------------------------------
   Parsing zero-arg terms
   ------------------------------ -}

parseTrue  = reserved "true"  >> return TmTrue

parseFalse = reserved "false" >> return TmFalse

parseUnit  = reserved "unit"  >> return TmUnit

parseNat = liftM numToSucc natural
    where numToSucc 0 = TmZero
          numToSucc n = TmSucc $ numToSucc (n - 1)

{- ------------------------------
   Arith Parsers
   ------------------------------ -}

parseOneArg keyword constructor = reserved keyword >> 
                                  liftM constructor parseTerm

parseSucc   = parseOneArg "succ"   TmSucc

parsePred   = parseOneArg "pred"   TmPred

parseIsZero = parseOneArg "iszero" TmIsZero

{- ------------------------------
   Other Parsers
   ------------------------------ -}

parseString = liftM TmString stringLiteral 

parseFloat = liftM TmFloat float

parseTimesFloat = reserved "timesfloat" >> 
                  liftM2 TmTimesFloat parseNonApp parseNonApp

parseIf = do reserved "if"
             t1 <- parseTerm
             reserved "then"
             t2 <- parseTerm
             reserved "else"
             liftM (TmIf t1 t2) parseTerm

parseVar = do var <- identifier
              if (isUpper $ var !! 0)
                then fail "variables must start with a lowercase letter"
                else do ctx <- getState
                        idx <- throwsToParser $ indexOf var ctx
                        return $ TmVar idx (ctxLength ctx)

parseInert = reserved "inert" >> squares (liftM TmInert parseType)

{- ------------------------------
   let/lambda
   ------------------------------ -}

-- for both let and lambda, we need to make sure we restore the
-- state after parsing the body, so that the lexical binding doesn't leak
parseAbs = do reserved "lambda"
              ctx <- getState
              (TmBind var (VarBind ty)) <- parseVarBind
              symbol "."
              body <- parseTerm
              setState ctx
              return $ TmAbs var ty body

parseLet = do reserved "let"
              ctx <- getState
              (TmBind var binding) <- (parseAbbBind False)
              reserved "in"
              body <- parseTerm
              setState ctx
              case binding of
                TmAbbBind t ty -> return $ TmLet var t body
                otherwise      -> fail "malformed let statement"

{- ------------------------------
   Fix and Letrec
   ------------------------------ -}

parseLetrec = do reserved "letrec"
                 ctx <- getState
                 (TmBind var binding) <- (parseAbbBind True)
                 reserved "in"
                 body <- parseTerm
                 setState ctx
                 case binding of
                   TmAbbBind t (Just ty)
                       -> return $ TmLet var (TmFix (TmAbs var ty t)) body
                   otherwise      
                       -> fail "malformed letrec statement"

parseFix = reserved "fix" >> liftM TmFix parseTerm

{- ------------------------------
   Records and Projections
   ------------------------------ -}

-- Fields can either be named or not.  If they are not, then they
-- are numbered starting with 1.  To keep parsing the fields simple,
-- we label them with -1 at first if they have no name.  We then
-- replace the -1's with the correct index as a post-processing step.
parseRecord = braces $ liftM TmRecord $ liftM (addNumbers 1) $ 
              sepBy parseRecordField comma
    where addNumbers _ [] = []
          addNumbers i (("-1",t):fs) = (show i, t) : (addNumbers (i+1) fs)
          addNumbers i (       f:fs) =           f : (addNumbers (i+1) fs)

parseRecordField = liftM2 (,) parseName parseTerm
    where parseName = (try (do {name <- identifier; symbol "="; return name}))
                      <|> return "-1"

parseProj = do t <- parseRecord <|> parens parseTerm
               symbol "."
               liftM (TmProj t) (identifier <|> (liftM show natural))

{- ------------------------------
   Variants and Cases
   ------------------------------ -}

parseVariant = do symbol "<"
                  var <- identifier
                  symbol "="
                  t <- parseTerm
                  symbol ">"
                  reserved "as"
                  liftM (TmTag var t) parseType

parseCase = do reserved "case"
               t <- parseTerm
               reserved "of"
               liftM (TmCase t) $ sepBy1 parseBranch (symbol "|")
    where parseBranch = do symbol "<"
                           label <- identifier
                           symbol "="
                           var <- identifier
                           symbol ">"
                           symbol "==>"
                           ctx <- getState
                           setState $ appendBinding var NameBind ctx
                           t <- parseTerm
                           setState ctx
                           return (label, (var,t))

{- ------------------------------
   Exceptions
   ------------------------------ -}

parseError = reserved "error" >> return (TmError TyBot)

parseTryWith = do reserved "try"
                  t1 <- parseTerm
                  reserved "with"
                  liftM (TmTry t1) parseTerm

{- ------------------------------
   Putting it all together
   ------------------------------ -}

parseNonApp = parseTrue <|>
              parseFalse <|>
              parseSucc <|>
              parsePred <|>
              parseIsZero <|>
              parseIf <|>
              (try parseFloat) <|>
              parseTimesFloat <|>
              parseNat <|>
              parseAbs <|>
              parseLet <|>
              (try parseBinder) <|>
              parseVar <|>
              parseUnit <|>
              parseString <|>
              (try parseProj) <|>
              parseRecord <|>
              parseCase <|>
              parseVariant <|>
              parseInert <|>
              parseFix <|>
              parseLetrec <|>
              parseError <|>
              parseTryWith <|>
              parens parseTerm

-- parses a non-application which could be an ascription
-- (the non-application parsing is left-factored)
parseNonAppOrAscribe = do t <- parseNonApp
                          (do reserved "as"
                              ty <- parseType
                              return $ TmAscribe t ty) <|> return t

-- For non-applications, we don't need to deal with associativity,
-- but we need to special handling (in the form of 'chainl1' here)
-- so that we enforce left-associativity as we aggregate a list of terms
parseTerm = chainl1 parseNonAppOrAscribe $ return TmApp

parseTerms = do whiteSpace -- lexer handles whitespace everywhere except here
                ts <- endBy1 parseTerm semi
                eof
                return ts

parseFullError :: String -> ThrowsError [Term]
parseFullError str 
    = case runParser parseTerms newContext "fullerror Parser" str of
        Left err -> throwError $ ParserError $ show err
        Right ts -> return ts

{- ------------------------------
   Helpers
   ------------------------------ -}

throwsToParser action = case action of
                          Left err  -> fail $ sho