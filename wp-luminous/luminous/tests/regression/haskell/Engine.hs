{-
    This file is part of Sarasvati.

    Sarasvati is free software: you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    Sarasvati is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.

    You should have received a copy of the GNU Lesser General Public
    License along with Sarasvati.  If not, see <http://www.gnu.org/licenses/>.

    Copyright 2008 Paul Lorenz
-}

-- Author: Paul Lorenz

module Workflow.Sarasvati.Engine (
                        GuardResponse(..),
                        NodeExtra(..),
                        Node(..),
                        NodeType(..),
                        Arc(..),
                        Token(..),
                        TokenAttr(..),
                        NodeToken(..),
                        ArcToken(..),
                        WfGraph(..),
                        ProcessState(..),
                        WfProcess(..),
                        WfEngine(..),
                        WfException(..),
                        tokenAttrValue,
                        tokenAttrValueReq,
                        completeExecution,
                        completeDefaultExecution,
                        evalGuardLang,
                        getNodeTokenForId,
                        graphFromArcs,
                        handleWfError,
                        isWfComplete,
                        makeNodeExtra,
                        nodeForToken,
                        processAttrValue,
                        startWorkflow,
                        replaceTokenAttrs,
                        tokenAttrs
                        ) where

import Control.Exception
import Control.Monad
import Data.Dynamic
import qualified Data.Map as Map
import qualified Workflow.Sarasvati.GuardLang as GuardLang
import qualified Workflow.Sarasvati.ListUtil as ListUtil

-- | Every 'Node' has a guard function which is called when a token arrives
--   and the node is ready to be activated. Guard functions must return a
--   'GuardResponse'. Guard functions are contained in 'NodeType' instances.
--   The nodeType attribute of a 'Node' is mapped to a 'NodeType' via the
--   nodeTypes map in a 'WfProcess'.
--
--   * 'AcceptToken'  - The token is passed on to the accept function
--
--   * 'DiscardToken' - The token is discarded and the accept function is not called
--
--   * 'SkipNode' arcName - The accept function is not called. The token is not discarded,
--                          the 'completeExecution' function is called instead with the
--                          given arc name

data GuardResponse = AcceptToken | DiscardToken | SkipNode String
  deriving (Show)

-- | 'NodeExtra' is a place to store any extra data that a given 'Node' may
-- require. The only requirement is that the 'extra data' be a 'Typeable'
-- so it can encapsulated in a 'Dynamic'.

data NodeExtra = NoNodeExtra | NodeExtra Dynamic

-- | Represents a node in a workflow graph. A Node is part of a process definition, as stored in
--   a 'WfGraph'. The 'Arc' type models connections between Nodes.
--
--   Members:
--
--     * 'nodeId'   - An Int id, which should be unique across all workflows. Used for testing equality.
--
--     * 'nodeType' - String which used to get the associated 'NodeType' stored in the 'WfProcess'
--
--     * 'nodeName' - String identifier which should be unique within a single process definition.
--
--     * 'nodeIsJoin' - If true, when an 'ArcToken' arrives at a 'Node', the node will wait for an 'ArcToken' to
--                      be waiting on every 'Arc' that shares the name of the 'Arc' that the current, incoming
--                      'ArcToken' points to. If false, every incoming 'ArcToken' will immediately generate a new
--                      'NodeToken' upon which the approprate guard function will be called.
--
--     * 'nodeIsStart' - If true a 'NodeToken' will e placed in this 'Node' when the workflow is started.
--
--     * 'nodeIsExternal' - True if this 'Node' was defined in an external process definition, False otherwise.
--
--     * 'nodeGuard' - May contain a string which can be interpreted by the guard function. For example, it may
--                     be a GuardLang script, which can be evaluated by the 'evalGuardLang' guard function.
--
--     * 'nodeExtra' - A 'NodeExtra', which may be nothing, or something 'Typeable', which the guard or accept
--                     functions may use to do their work.

data Node =
    Node {
        nodeId         :: Int,
        nodeType       :: String,
        nodeName       :: String,
        nodeIsJoin     :: Bool,
        nodeIsStart    :: Bool,
        nodeIsExternal :: Bool,
        nodeGuard      :: String,
        nodeExtra      :: NodeExtra
    }

-- | Encapsulates the behavior for a specific 'Node' type. Each type of 'Node' can have
--   different behaviour for guards and for accept.
--
--   * 'guardFunction' - Called when a 'NodeToken' is created in a 'Node'. The 'GuardResponse'
--                       result determines if the related 'acceptFunction' is called, if the
--                       token is discarded, if accept is skipped and 'completeExecution' is
--                       is called.
--
--   * 'acceptFunction' - Called if 'guardFunction' returns 'AcceptToken'. The function may
--                        end with a call to 'completeExecution', or it may be called later.

data NodeType a =
    NodeType {
        guardFunction  :: (NodeToken -> WfProcess a -> IO GuardResponse),
        acceptFunction :: (WfEngine engine) => (engine -> NodeToken -> WfProcess a -> IO (WfProcess a))
    }


-- | An 'Arc' represents an directed edge in a workflow graph.
--   It has an id, a label and two node id endpoints.
--
-- * 'arcId' - *Int* id, which should be unique for that process definition.
--
-- * 'arcName' - Every 'Arc' has a name, though that name will often be the default name,
--               which is just the empty string. When 'completeExecution' is called on a
--               'NodeToken' an arc name must be specified. More than one 'Arc' may have
--               the same name. Every arc with the given name will have an 'ArcToken'
--               placed on it. There may also be no arcs with that name.
--
-- * startNodeId - The id of the *Node* at the beginning of this arc.
--
-- * endNodeId   - The id of the *Node* at the end of this arc.

data Arc =
    Arc {
        arcId        :: Int,
        arcName      :: String,
        startNodeId  :: Int,
        endNodeId    :: Int
    }
 deriving (Show)


-- Tokens are split into NodeTokens and ArcTokens. NodeTokens are sitting at
-- nodes in the workflow graph while ArcTokens are 'in-transit' and are on
-- Arcs.
--
-- The Token class allows NodeTokens and ArcTokens to share an id lookup function

class Token a where
   tokenId   :: a -> Int

data TokenAttr =
    TokenAttr {
        attrSetId           :: Int,
        tokenAttributeKey   :: String,
        tokenAttributeValue :: String
    }
  deriving (Show)

-- NodeToken represents tokens which are at node
--   The NodeToken constructor takes three parameters
--   token id :: Int          - The id should be unique among node tokens for this process
--   node  id :: Int          - This should be the id of a node in the graph for this process
data NodeToken = NodeToken Int Int
    deriving (Show)

-- ArcToken represents tokens which are between nodes (on an arc)

data ArcToken = ArcToken Int Arc NodeToken
    deriving (Show)


-- WFGraph
--   Has the set of nodes as well as maps of node input arcs and node output arcs
--   keyed by node id.

data WfGraph =
    WfGraph {
       graphId         :: Int,
       graphName       :: String,
       graphNodes      :: Map.Map Int Node,
       graphInputArcs  :: Map.Map Int [Arc],
       graphOutputArcs :: Map.Map Int [Arc]
    }

data ProcessState = ProcessCreated | ProcessExecuting | ProcessComplete | ProcessCanceled
    deriving (Eq, Show)

-- A WfProcess tracks the current state of the workflow. It has the workflow graph as well
-- as the tokens representing the current state. A slot for user data is also defined.

data WfProcess a =
    WfProcess {
        processId    :: Int,
        processState :: ProcessState,
        nodeTypes    :: Map.Map String (NodeType a),
        wfGraph      :: WfGraph,
        nodeTokens   :: [NodeToken],
        arcTokens    :: [ArcToken],
        attrMap      :: Map.Map String String,
        tokenAttrMap :: Map.Map Int [TokenAttr],
        predicateMap :: Map.Map String (NodeToken -> WfProcess a -> IO Bool),
        userData     :: a
    }

class WfEngine a where
    createWfProcess     :: a -> WfGraph ->
                                Map.Map String (NodeType b) ->                          -- Map of type name to NodeType
                                Map.Map String (NodeToken -> WfProcess b -> IO Bool) -> -- Map of predicate names to predicate functions. Used by GuardLang
                                b ->                                                    -- The initial user data
                                Map.Map String String ->                                -- The initial process attributes
                                IO (WfProcess b)
    createNodeToken     :: a -> WfProcess b -> Node -> [ArcToken] -> IO (WfProcess b, NodeToken)
    createArcToken      :: a -> WfProcess b -> Arc  -> NodeToken  -> IO (WfProcess b, ArcToken)
    completeNodeToken   :: a -> NodeToken   -> IO ()
    completeArcToken    :: a -> ArcToken    -> IO ()
    recordGuardResponse :: a -> NodeToken -> GuardResponse -> IO ()
    transactionBoundary :: a -> IO ()
    setProcessAttr      :: a -> WfProcess b -> String -> String -> IO (WfProcess b)
    removeProcessAttr   :: a -> WfProcess b -> String -> IO (WfProcess b)
    setTokenAttr        :: a -> WfProcess b -> NodeToken -> String -> String -> IO (WfProcess b)
    removeTokenAttr     :: a -> WfProcess b -> NodeToken -> String -> IO (WfProcess b)
    setProcessState     :: a -> WfProcess b -> ProcessState -> IO (WfProcess b)


instance Show (NodeExtra) where
    show NoNodeExtra = "NoNodeExtra"
    show _           = "NodeExtra: Dynamic"


instance Show (Node) where
    show a = "|Node id: " ++ (show.nodeId) a ++ " name: " ++ nodeName a ++ " type: " ++ nodeType a ++
             "  isJoin: " ++ (show.nodeIsJoin) a ++ "  isStart: " ++ (show.nodeIsStart) a ++
             "  isExternal: " ++ (show.nodeIsExternal) a ++ "|"

instance Token (NodeToken) where
    tokenId (NodeToken tokId _) = tokId

instance Eq (NodeToken) where
    tok1 == tok2 = (tokenId tok1) == (tokenId tok2)

instance Token (ArcToken) where
    tokenId (ArcToken tokId _ _) = tokId

instance Eq (ArcToken) where
    tok1 == tok2 = (tokenId tok1) == (tokenId tok2)

data WfException = WfException String
  deriving (Show,Typeable)

wfError :: String -> a
wfError msg = throwDyn $ WfException msg

handleWfError :: (WfException -> IO a) -> IO a -> IO a
handleWfError f a = catchDyn a f

makeNodeExtra :: (Typeable a) => a -> NodeExtra
makeNodeExtra extra = NodeExtra $ toDyn extra

tokenAttrs :: WfProcess a -> NodeToken -> [TokenAttr]
tokenAttrs wfProcess token = (tokenAttrMap wfProcess) Map.! (tokenId token)

tokenAttrValueReq :: WfProcess a -> NodeToken -> String -> String
tokenAttrValueReq process nodeToken key =
    case (attr) of (TokenAttr _ _ value) -> value
    where
        attr  = head $ filter (\tokenAttr -> tokenAttributeKey tokenAttr == key) (tokenAttrs process nodeToken)

tokenAttrValue :: WfProcess a -> NodeToken -> String -> Maybe String
tokenAttrValue process nodeToken key =
    case (attr) of
        [(TokenAttr _ _ value)] -> Just value
        _                       -> Nothing
    where
        attr  = filter (\tokenAttr -> tokenAttributeKey tokenAttr == key) (tokenAttrs process nodeToken)

processAttrValue :: WfProcess a -> String -> Maybe String
processAttrValue process key
    | Map.member key (attrMap process) = Just $ (attrMap process) Map.! key
    | otherwise                        = Nothing

replaceTokenAttrs :: WfProcess a -> NodeToken -> [TokenAttr] -> WfProcess a
replaceTokenAttrs process token attrList =
    process { tokenAttrMap = Map.insert (tokenId token) attrList (tokenAttrMap process) }

-- showGraph
--   Print prints a graph

instance Show (WfGraph) where
  show graph = graphName graph ++ ":\n" ++
                 concatMap (\a->show a ++ "\n") (Map.elems (graphNodes graph)) ++ "\n" ++
                 concatMap (\a->show a ++ "\n") (Map.elems (graphInputArcs graph)) ++ "\n" ++
                 concatMap (\a->show a ++ "\n") (Map.elems (graphOutputArcs graph))

-- | Given a name, version, and lists of nodes and arcs, builds the lookup lists
--   and returns a 'WfGraph'.

graphFromArcs :: Int -> String -> [Node] -> [Arc] -> WfGraph
graphFromArcs graphId name nodes arcs = WfGraph graphId name nodeMap inputsMap outputsMap
    where
        nodeMap  = Map.fromList $ zip (map nodeId nodes) nodes

        inputsMap             = Map.fromList $ zip (map nodeId nodes) (map inputArcsForNode nodes)
        inputArcsForNode node = filter (\arc -> endNodeId arc == nodeId node) arcs

        outputsMap = Map.fromList $ zip (map nodeId nodes) (map outputArcsForNode nodes)
        outputArcsForNode node = filter (\arc -> startNodeId arc == nodeId node) arcs

-- getTokenForId
--  | Given a token id and a workflow instance gives back the actual token
--    corresponding to that id

getNodeTokenForId :: Int -> WfProcess a -> NodeToken
getNodeTokenForId tokId wf =
  head $ filter (\t -> (tokenId t) == tokId) (nodeTokens wf)

-- Convenience lookup methods for the data pointed to by tokens

nodeForToken :: NodeToken -> WfGraph -> Node
nodeForToken (NodeToken _ nodeId) graph = (graphNodes graph) Map.! nodeId

arcForToken :: ArcToken -> Arc
arcForToken  (ArcToken _ arc _)           = arc

-- startWorkflow
--   Given a workflow definition (WfGraph) and initial userData, gives
--   back a new in progress workflow instance for that definition.

startWorkflow :: (WfEngine engine) =>
                   engine ->
                   Map.Map String (NodeType a) ->
                   Map.Map String (NodeToken -> WfProcess a -> IO Bool) ->
                   Map.Map String String ->
                   WfGraph -> a -> IO ( Either String (WfProcess a))
startWorkflow engine nodeTypes predicates attrs graph userData
    | typesMissing          = return $ Left ("Missing entries in nodeType for: " ++ missingMsg)
    | null startNodes       = return $ Left "Error: Workflow has no start node"
    | length startNodes > 1 = return $ Left "Error: Workflow has more than one start node"
    | otherwise             = do wf <- createWfProcess engine graph nodeTypes predicates userData attrs
                                 (wf,startToken) <- createNodeToken engine wf startNode []
                                 wf <- setProcessState engine wf ProcessExecuting
                                 wf <- acceptWithGuard engine startToken (wf { nodeTokens = [startToken] })
                                 wf <- if (isWfComplete wf)
                                           then setProcessState engine wf ProcessComplete
                                           else return wf
                                 return $ Right wf
  where
    startNodes   = filter (\node -> nodeIsStart node) $ Map.elems (graphNodes graph)
    startNode    = head startNodes
    typesMissing = (not.null) missingTypes
    missingTypes = filter (\node-> not (Map.member (nodeType node) nodeTypes)) (Map.elems (graphNodes graph))
    missingMsg   = concatMap (\node -> nodeType node ++ " ") missingTypes


isWfComplete :: WfProcess a -> Bool
isWfComplete process = null (nodeTokens process) && null (arcTokens process)

-- removeNodeToken
--   Removes the node token from the list of active node tokens in the given process

removeNodeToken :: NodeToken -> WfProcess a -> WfProcess a
removeNodeToken token wf = wf { nodeTokens = ListUtil.removeFirst (\t->t == token) (nodeTokens wf) }

completeDefaultExecution :: (WfEngine engine) => engine -> NodeToken -> WfProcess a -> IO (WfProcess a)
completeDefaultExecution engine token wf = completeExecution engine token [] wf

-- completeExecution
--   Generates a new token for each output node of the current node of the given
--   token.

completeExecution :: (WfEngine e) => e -> NodeToken -> String -> WfProcess a -> IO (WfProcess a)
completeExecution engine token outputArcName wf =
  if ( processState wf /= ProcessExecuting )
      then return wf
      else do completeNodeToken engine token
              newWf <- foldM (split) newWf outputArcs
              if (isWfComplete newWf)
                 then setProcessState engine newWf ProcessComplete
                 else return newWf
  where
    graph        = wfGraph wf
    currentNode  = nodeForToken token graph
    outputArcs   = filter (\arc -> arcName arc == outputArcName ) $
                   (graphOutputArcs graph) Map.! (nodeId currentNode)

    newWf        = removeNodeToken token wf

    split wf arc = do (wf, arcToken) <- createArcToken engine wf arc token
                      acceptToken engine arcToken wf

-- acceptToken
--   Called when a token arrives at a node. The node is checked to see if it requires
--   tokens at all inputs. If it doesn't, the acceptSingle function is called. Otherwise
--   it calls acceptJoin.

acceptToken :: (WfEngine e) => e -> ArcToken -> WfProcess a -> IO (WfProcess a)
acceptToken engine token wf
    | isAcceptSingle = acceptSingle engine token wf
    | otherwise      = acceptJoin   engine token wf
  where
    isAcceptSingle = not $ nodeIsJoin targetNode
    targetNode     = ((graphNodes.wfGraph) wf) Map.! ((endNodeId.arcForToken) token)

-- acceptSingle
--   Called when a node requires only a single incoming token to activate.
--   Moves the token into the node and calls the guard function

acceptSingle :: (WfEngine e) => e -> ArcToken -> WfProcess a -> IO (WfProcess a)
acceptSingle engine token process =
  do (process,newToken) <- createNodeToken engine process node [token]
     completeArcToken engine token
     acceptWithGuard engine newToken process { nodeTokens = newToken:(nodeTokens process) }
  where
    graph = wfGraph process
    node  = (graphNodes graph) Map.! ((endNodeId.arcForToken) token)

-- acceptJoin
--   Called when a node requires that a token exist at all inputs before activating.
--   If the condition is met, joins all the input tokens into a single token in the
--   node then calls the guard function.
--   If all inputs don't yet have inputs, adds the current token to the workflow
--   instance and returns.

acceptJoin :: (WfEngine e) => e -> ArcToken -> WfProcess a -> IO (WfProcess a)
acceptJoin engine token process
    | areAllInputsPresent = do (process,newToken) <- createNodeToken engine process targetNode inputTokens
                               let newProcess = process { nodeTokens = newToken:(nodeTokens process), arcTokens = outputArcTokens }
                               mapM (completeArcToken engine) inputTokens
                               acceptWithGuard engine newToken newProcess
    | otherwise           = return process { arcTokens = allArcTokens }
  where
    allArcTokens          = token:(arcTokens process)
    areAllInputsPresent   = length inputTokens == length inputArcs

    fstInputArcToken arc  = ListUtil.firstMatch (\arcToken -> (arcId.arcForToken) arcToken == arcId arc) allArcTokens

    inputTokens           = ListUtil.removeNothings $ map (fstInputArcToken) inputArcs

    targetNodeId          = (endNodeId.arcForToken) token
    targetNode            = (graphNodes (wfGraph process)) Map.! targetNodeId
    allInputArcs          = (graphInputArcs (wfGraph process)) Map.! targetNodeId
    inputArcs             = filter (\arc-> arcName arc == (arcName.arcForToken) token) allInputArcs
    outputArcTokens       = filter (\t -> not $ elem t inputTokens) (arcTokens process)

-- acceptWithGuard
--   This is only called once the node is ready to fire. The given token is now in the node
--   and exists in the workflow instance.
--   The node guard method is now called and the appropriate action will be taken based on
--   what kind of GuardResponse is returned.

acceptWithGuard :: (WfEngine e) => e -> NodeToken -> WfProcess a -> IO (WfProcess a)
acceptWithGuard engine token wf =
    do guardResponse <- guard token wf
       case guardResponse of
           AcceptToken    -> accept engine token wf
           DiscardToken   -> do completeNodeToken engine token
                                return $ removeNodeToken token wf
           (SkipNode arc) -> completeExecution engine token arc wf
    where
        currentNode  = nodeForToken token (wfGraph wf)
        guard        = guardFunction  currNodeType
        accept       = acceptFunction currNodeType
        currNodeType = (nodeTypes wf) Map.! (nodeType currentNode)

evalGuardLang :: NodeToken -> WfProcess a -> IO GuardResponse
evalGuardLang token wf
    | null guard = return $ AcceptToken
    | otherwise  = do result <- evalGuardLangStmt token wf $ GuardLang.evalGuard (GuardLang.lexer guard)
                      return $ resultToResponse result
    where
        node  = nodeForToken token (wfGraph wf)
        guard = nodeGuard node

resultToResponse :: GuardLang.Result -> GuardResponse
resultToResponse GuardLang.Accept     = AcceptToken
resultToResponse GuardLang.Discard    = DiscardToken
resultToResponse (GuardLang.Skip arc) = SkipNode arc

evalGuardLangStmt :: NodeToken -> WfProcess a -> GuardLang.Stmt -> IO GuardLang.Result
evalGuardLangStmt _     _  (GuardLang.StmtResult result)           = return result
evalGuardLangStmt token wf (GuardLang.StmtIF expr ifStmt elseStmt) =
    do result <- evalGuardLangExpr token wf expr
       case result of
          True  -> evalGuardLangStmt token wf ifStmt
          False -> evalGuardLangStmt token wf elseStmt

evalGuardLangExpr :: NodeToken -> WfProcess a -> GuardLang.Expr -> IO Bool
evalGuardLangExpr token wf (GuardLang.ExprSymbol symbol)   = evalGuardLangPred token wf symbol
evalGuardLangExpr token wf (GuardLang.ExprOR  exprL exprR) =
    do result <- evalGuardLangExpr token wf exprL
       case result of
           True  -> return True
           False -> evalGuardLangExpr token wf exprR
evalGuardLangExpr token wf (GuardLang.ExprAND exprL exprR) =
    do result <- evalGuardLangExpr token wf exprL
       case result of
           True  -> evalGuardLangExpr token wf exprR
           False -> return False
evalGuardLangExpr token wf (GuardLang.ExprNOT expr) =
    do result <- evalGuardLangExpr token wf expr
       return $ not result

evalGuardLangPred :: NodeToken -> WfProcess a -> String -> IO Bool
evalGuardLangPred token wf predicate
    | invalidPredicate = wfError $ "Predicate " ++ predicate ++ " not defined"
    | otherwise        = (predMap Map.! predicate) token wf
    where
        predMap = predicateMap wf
        invalidPredicate = not (Map.member predicate predMap)