module WS.Runtime.Xmlable where 

import System.Locale
import Data.List
import qualified Data.Map as Map
import Data.Time.LocalTime
import Data.Time.Format
import Data.Maybe
import Data.Typeable
import Data.Char
import Data.Function
import Control.Arrow
import Control.Monad
import Text.XML.Light
import Data.Ord

mkContent :: Attr -> Content
mkContent a = Elem Element { elName = attrKey a, elAttribs = [], 
        elContent = [Text $ CData CDataRaw (attrVal a) Nothing], elLine = Nothing }
        
concatAttr :: [([Attr], [Content])] -> [[Content]]
concatAttr = map (uncurry (++) . first (map mkContent))
        
sortContent :: [String] -> [[Content]] -> [[Content]]
sortContent ss = map (sortBy $ comparing nameIndex) . filter (not . null)
    where 
        nameIndex c = case c of
            Elem e  -> np e `elemIndex` ss
            _ -> Nothing
        name = qName . elName
        pref = qPrefix . elName
        np e 
            | isNothing $ pref e = name e
            | otherwise = fromJust (pref e) ++ ":" ++ name e

class Typeable a => Xmlable a where
    toContent :: a -> [[Content]]
    fromContent :: [[Content]] -> a
    toAttrContent :: a -> [([Attr], [Content])]
    toAttrContent = map ((,) []) . toContent
    fromAttrContent :: [([Attr], [Content])] -> a
    fromAttrContent = fromContent . concatAttr
    toContent = concatAttr . toAttrContent
    fromContent = fromAttrContent . map ((,) [])
    
instance Xmlable Char where
    toContent _ = []
    fromContent _ = ' '
    
instance Xmlable Bool where
    toContent b = [[Text $ CData CDataText (if b then "true" else "false") Nothing]]
    fromContent [[Text (CData CDataText s _)]] = s == "true"
    fromContent _ = error "Invalid boolean value in fromContent::(Xmlable Bool)"
    
instance Xmlable LocalTime where
    toContent t = [[Text $ CData CDataText (formatTime defaultTimeLocale "%FT%X" t) Nothing]]
    fromContent [[Text (CData CDataText s _)]] = readTime defaultTimeLocale "%FT%T" $ fst.break (=='.') $ s
    fromContent _ = error "Invalid LocalTime value in fromContent::(Xmlable LocalTime)"

instance Xmlable a => Xmlable (Maybe a) where
    toContent Nothing = []
    toContent (Just x) = toContent x
    toAttrContent Nothing = []
    toAttrContent (Just x) = toAttrContent x
    fromContent [] = Nothing
    fromContent cs = Just (fromContent cs)
    fromAttrContent [] = Nothing
    fromAttrContent acs = Just (fromAttrContent acs)

instance Xmlable a => Xmlable [a] where
    toContent [] = []
    toContent s = if show (typeOf s) == "[Char]" then [[Text $ CData CDataRaw (fromMaybe "" $ cast s) Nothing]] else concatMap toContent s
    fromContent [] = []
    -- fromContent [[]] = []
    fromContent cs = case cs of 
        [[Text (CData _ s _)]] -> fromMaybe [] $ cast s
        _ | show (typeOf getList) == "[Char]" -> fromMaybe [] $ cast $ concatMap showContent $ head cs
          | otherwise -> getList
        where 
            getList = map (\x->fromContent [x]) cs
    toAttrContent [] = []
    toAttrContent s = if show (typeOf s) == "[Char]" then [([], [Text $ CData CDataRaw (fromMaybe "" $ cast s) Nothing])] else concatMap toAttrContent s
    fromAttrContent [] = []
    fromAttrContent cs = case cs of 
        [([],[Text (CData _ s _)])] -> fromMaybe [] $ cast s
        _ | show (typeOf getList) == "[Char]" -> fromMaybe [] $ cast $ (concatMap showContent $ snd $ head cs) ++ (concatMap attrVal $ fst $ head cs)
          | otherwise -> getList
        where 
            getList = map (\x->fromAttrContent [x]) cs
    
        
instance (Xmlable k, Xmlable a) => Xmlable (k, a) where
    toContent s = [makeToContent ["fst", "snd"] [toContent . fst, toContent . snd] s]
    fromContent cs = (fromContent k, fromContent a)
        where [k, a] = forFromContent [] ["fst", "snd"] cs
    toAttrContent s = [makeToAttrContent [] [] ["fst", "snd"] [toAttrContent . fst, toAttrContent . snd] s]
    fromAttrContent acs = (fromAttrContent k, fromAttrContent a)
        where [k, a] = forFromAttrContent [] ["fst", "snd"] acs

instance (Xmlable k, Ord k, Xmlable a) => Xmlable (Map.Map k a) where
    toContent = toContent . Map.toList
    fromContent = Map.fromList . fromContent
    toAttrContent = toAttrContent . Map.toList
    fromAttrContent = Map.fromList . fromAttrContent

toContentSimple :: (Typeable a, Show a) => a -> [[Content]]
toContentSimple x = [[Text $ CData CDataText (show x) Nothing]]

fromContentSimple :: (Typeable a, Show a, Read a) => [[Content]] -> a
fromContentSimple [[Text (CData _ s _)]] = read s
fromContentSimple x = error $ unlines ["Invalid value in fromContentSimple:", show x]

instance Xmlable Float where
    toContent = toContentSimple
    fromContent = fromContentSimple

instance Xmlable Integer where
    toContent = toContentSimple
    fromContent = fromContentSimple
     
makeToContent :: (Xmlable t) => [String] -> [t -> [[Content]]] -> t -> [Content]
makeToContent ss gs x = concat $ zipWith makeToContent' ss gs
    where
        makeToContent' n g = map (Elem . unode n) (g x)

makeToAttrContent :: (Xmlable t) => [String] -> [t -> [([Attr], [Content])]] -> [String] -> [t -> [([Attr], [Content])]] -> t -> ([Attr], [Content])
makeToAttrContent sas gas scs gcs x = (catMaybes $ zipWith mkAttr sas gas, concat $ zipWith makeToContent' scs gcs)
    where
        mkAttr s g = case g x of 
            [([],[Text (CData _ str _)])] -> Just $ Attr {attrKey = unqual s, attrVal = str}
            [] -> Nothing
            y -> error $ unlines ["Content of attribute is not a Text:", show y]
        makeToContent' n g = map (Elem . unode n) (g x)
        
getPrefName :: String -> (String, String)
getPrefName n 
    | ':' `elem` n = break (==':') >>> second (dropWhile (`elem` ":/")) $ n
    | otherwise = ("", n)
    
forFromContent :: [(String, String)] -> [String] -> [[Content]] -> [[[Content]]]
forFromContent nss ss = map (map snd) . forFromContentAC nss ss

forFromContentAC :: [(String, String)] -> [String] -> [[Content]] -> [[([Attr], [Content])]]
forFromContentAC _ ss [] = error $ unlines ["forFromContentAC: Content is empty. Fields are: ", show ss]
forFromContentAC nss ss x = pairs ss . groupBy ((==) `on` nam) . changePrefixInElems . onlyElems . head $ x
    where
        pairs names [] = map (const []) names
        pairs (n:ns) ccs@(c:cs)
            | null c = error "forFromContentAC: head of [[Content]] is empty"
            | nam (head c) == getPrefName n = map (elAttribs &&& elContent) c : pairs ns cs
            | otherwise = [] : pairs ns ccs
        pairs names ccs = error ("forFromContentAC: Invalid pairs parameters: \n" ++ show names ++ "\n" ++ show ccs)
        changePrefixInElems = map (\e -> 
                e { elName = (elName e) { qPrefix = liftM fst $ qURI (elName e) >>= flip find nss . (snd >>>) . (==) } })
        nam = elName >>> (qPrefix >>> fromMaybe "") &&& qName

forFromAttrContent :: [(String, String)] -> [String] -> [([Attr], [Content])] -> [[([Attr], [Content])]]
forFromAttrContent nss ss = forFromContentAC nss ss . sortContent ss . concatAttr