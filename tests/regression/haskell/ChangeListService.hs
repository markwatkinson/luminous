{-# OPTIONS_GHC -XDeriveDataTypeable #-}
module ChangeListService where
import Data.List
import Data.Maybe
import Data.Typeable
import Data.Time.LocalTime
import XI.Directory.WS.Xmlable
import XI.Directory.WS.Main

ws = "ChangeListService"
nss = [("urn0","urn:com.sap.aii.ibdir.server.api.types"),("urn1","urn:com.sap.aii.ib.server.api.types"),("urn2","urn:ChangeListServiceVi")]

-- operations

activate :: WsParams -> ActivateInput -> IO ActivateOutput
activate = runService ws nss "urn2:activate"
checkContent :: WsParams -> CheckContentInput -> IO CheckContentOutput
checkContent = runService ws nss "urn2:checkContent"
create :: WsParams -> CreateInput -> IO CreateOutput
create = runService ws nss "urn2:create"
getCacheState :: WsParams -> GetCacheStateInput -> IO GetCacheStateOutput
getCacheState = runService ws nss "urn2:getCacheState"
getObjectIdentifiers :: WsParams -> GetObjectIdentifiersInput -> IO GetObjectIdentifiersOutput
getObjectIdentifiers = runService ws nss "urn2:getObjectIdentifiers"
getState :: WsParams -> GetStateInput -> IO GetStateOutput
getState = runService ws nss "urn2:getState"
revert :: WsParams -> RevertInput -> IO RevertOutput
revert = runService ws nss "urn2:revert"


-- types definition

data ActivateInput = ActivateInput {
    cL_ActivateRequest :: Maybe String
} deriving (Typeable, Show, Eq) 

data ActivateOutput = ActivateOutput {
    responseActivateOutput :: Maybe LM_Collection
} deriving (Typeable, Show, Eq) 

data LM_Collection = LM_Collection {
    lmcLogMessage :: Maybe [LogMessage],
    lmcLM_ChangeList :: Maybe [LM_ChangeList],
    lmcLM_Party :: Maybe [LM_Party],
    lmcLM_BusinessSystem :: Maybe [LM_CommunicationComponent],
    lmcLM_BusinessComponent :: Maybe [LM_CommunicationComponent],
    lmcLM_IntegrationProcess :: Maybe [LM_CommunicationComponent],
    lmcLM_CommunicationChannel :: Maybe [LM_CommunicationChannel],
    lmcLM_SenderAgreement :: Maybe [LM_MessageHeader],
    lmcLM_ReceiverAgreement :: Maybe [LM_MessageHeader],
    lmcLM_InterfaceDetermination :: Maybe [LM_MessageHeader],
    lmcLM_ReceiverDetermination :: Maybe [LM_MessageHeader],
    lmcLM_ValueMapping :: Maybe [LM_ValueMapping],
    lmcLM_ConfigurationScenario :: Maybe [LM_ConfigurationScenario]
} deriving (Typeable, Show, Eq) 

data LogMessage = LogMessage {
    lmLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data LM_Item = LM_Item {
    lmiSeverityCode :: Maybe String,
    lmiClassificationCode :: Maybe String,
    lmiMessage :: Maybe Text
} deriving (Typeable, Show, Eq) 

data Text = Text {
    tlanguageCode :: Maybe String,
    tvalue :: Maybe String
} deriving (Typeable, Show, Eq) 

data LM_ChangeList = LM_ChangeList {
    lmclCL_ID :: Maybe CL_ID,
    lmclLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data CL_ID = CL_ID {
    clidCL_ID :: Maybe String,
    clidName :: Maybe String,
    clidDescription :: Maybe LONG_Description
} deriving (Typeable, Show, Eq) 

data LONG_Description = LONG_Description {
    ldlanguageCode :: Maybe String,
    ldvalue :: Maybe String
} deriving (Typeable, Show, Eq) 

data LM_Party = LM_Party {
    lmpPI_D :: Maybe String,
    lmpLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data LM_CommunicationComponent = LM_CommunicationComponent {
    lmccmpCC_ID :: Maybe Cmp_ID,
    lmccmpLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data Cmp_ID = Cmp_ID {
    ccidPI_D :: Maybe String,
    ccidCI_D :: Maybe String
} deriving (Typeable, Show, Eq) 

data LM_CommunicationChannel = LM_CommunicationChannel {
    lmccCC_ID :: Maybe CC_ID,
    lmccLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data CC_ID = CC_ID {
    ccidPartyID :: Maybe String,
    ccidComponentID :: Maybe String,
    ccidChannelID :: Maybe String
} deriving (Typeable, Show, Eq) 

data LM_MessageHeader = LM_MessageHeader {
    lmmhMessageHeader :: Maybe MH_ID,
    lmmhLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data MH_ID = MH_ID {
    mhidSP_ID :: Maybe String,
    mhidSC_ID :: Maybe String,
    mhidInterfaceName :: Maybe String,
    mhidInterfaceNamespace :: Maybe String,
    mhidRP_ID :: Maybe String,
    mhidRC_ID :: Maybe String
} deriving (Typeable, Show, Eq) 

data LM_ValueMapping = LM_ValueMapping {
    lmvmVM_ID :: Maybe String,
    lmvmLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data LM_ConfigurationScenario = LM_ConfigurationScenario {
    lmcsCS_ID :: Maybe String,
    lmcsLM_Item :: Maybe LM_Item
} deriving (Typeable, Show, Eq) 

data CheckContentInput = CheckContentInput {
    cL_CheckContentRequest :: Maybe String
} deriving (Typeable, Show, Eq) 

data CheckContentOutput = CheckContentOutput {
    responseCheckContentOutput :: Maybe LM_Collection
} deriving (Typeable, Show, Eq) 

data CreateInput = CreateInput {
    cL_CreateRequest :: Maybe CL_IDRestricted
} deriving (Typeable, Show, Eq) 

data CL_IDRestricted = CL_IDRestricted {
    clidrName :: Maybe String,
    clidrDescription :: Maybe LONG_Description
} deriving (Typeable, Show, Eq) 

data CreateOutput = CreateOutput {
    responseCreateOutput :: Maybe CL_CreateOut
} deriving (Typeable, Show, Eq) 

data CL_CreateOut = CL_CreateOut {
    clcoCL_ID :: Maybe CL_ID,
    clcoLM_Collection :: Maybe LM_Collection
} deriving (Typeable, Show, Eq) 

data GetCacheStateInput = GetCacheStateInput {
    cL_GetCacheStateRequest :: Maybe String
} deriving (Typeable, Show, Eq) 

data GetCacheStateOutput = GetCacheStateOutput {
    responseGetCacheStateOutput :: Maybe CL_GetCacheStateOut
} deriving (Typeable, Show, Eq) 

data CL_GetCacheStateOut = CL_GetCacheStateOut {
    clgcsoCacheState :: Maybe [CL_CacheState],
    clgcsoLM_Collection :: Maybe LM_Collection
} deriving (Typeable, Show, Eq) 

data CL_CacheState = CL_CacheState {
    clcsConsumer :: Maybe String,
    clcsNotificationState :: Maybe String,
    clcsRefreshState :: Maybe String,
    clcsErrorMessage :: Maybe [LM_Item]
} deriving (Typeable, Show, Eq) 

data GetObjectIdentifiersInput = GetObjectIdentifiersInput {
    cL_GetObjectIdentifiersRequest :: Maybe String
} deriving (Typeable, Show, Eq) 

data GetObjectIdentifiersOutput = GetObjectIdentifiersOutput {
    responseGetObjectIdentifiersOutput :: Maybe CL_GetObjectIdentifiersOut
} deriving (Typeable, Show, Eq) 

data CL_GetObjectIdentifiersOut = CL_GetObjectIdentifiersOut {
    clgoioPI_D :: Maybe [String],
    clgoioBS_ID :: Maybe [Cmp_ID],
    clgoioBC_ID :: Maybe [Cmp_ID],
    clgoioIP_ID :: Maybe [Cmp_ID],
    clgoioCC_ID :: Maybe [CC_ID],
    clgoioSA_ID :: Maybe [MH_ID],
    clgoioRA_ID :: Maybe [MH_ID],
    clgoioRD_ID :: Maybe [MH_ID],
    clgoioID_ID :: Maybe [MH_ID],
    clgoioVM_ID :: Maybe [String],
    clgoioCS_ID :: Maybe [String],
    clgoioLM_Collection :: Maybe LM_Collection
} deriving (Typeable, Show, Eq) 

data GetStateInput = GetStateInput {
    cL_GetStateRequest :: Maybe String
} deriving (Typeable, Show, Eq) 

data GetStateOutput = GetStateOutput {
    responseGetStateOutput :: Maybe CL_GetStateOut
} deriving (Typeable, Show, Eq) 

data CL_GetStateOut = CL_GetStateOut {
    clgsoState :: Maybe String,
    clgsoLM_Collection :: Maybe LM_Collection
} deriving (Typeable, Show, Eq) 

data RevertInput = RevertInput {
    cL_RevertRequest :: Maybe String
} deriving (Typeable, Show, Eq) 

data RevertOutput = RevertOutput {
    responseRevertOutput :: Maybe LM_Collection
} deriving (Typeable, Show, Eq) 



-- instances


instance Xmlable ActivateInput where
        toContent x = [makeToContent ["urn2:ChangeListActivateRequest"] [toContent.cL_ActivateRequest] x]
        fromContent cs = ActivateInput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:ChangeListActivateRequest"] cs

instance Xmlable ActivateOutput where
        toContent x = [makeToContent ["urn2:Response"] [toContent.responseActivateOutput] x]
        fromContent cs = ActivateOutput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:Response"] cs

instance Xmlable LM_Collection where
        toContent x = [makeToContent ["urn0:LogMessage", "urn0:LogMessageChangeList", "urn0:LogMessageParty", "urn0:LogMessageBusinessSystem", "urn0:LogMessageBusinessComponent", "urn0:LogMessageIntegrationProcess", "urn0:LogMessageCommunicationChannel", "urn0:LogMessageSenderAgreement", "urn0:LogMessageReceiverAgreement", "urn0:LogMessageInterfaceDetermination", "urn0:LogMessageReceiverDetermination", "urn0:LogMessageValueMapping", "urn0:LogMessageConfigurationScenario"] [toContent.lmcLogMessage, toContent.lmcLM_ChangeList, toContent.lmcLM_Party, toContent.lmcLM_BusinessSystem, toContent.lmcLM_BusinessComponent, toContent.lmcLM_IntegrationProcess, toContent.lmcLM_CommunicationChannel, toContent.lmcLM_SenderAgreement, toContent.lmcLM_ReceiverAgreement, toContent.lmcLM_InterfaceDetermination, toContent.lmcLM_ReceiverDetermination, toContent.lmcLM_ValueMapping, toContent.lmcLM_ConfigurationScenario] x]
        fromContent cs = LM_Collection (fromContent c1) (fromContent c2) (fromContent c3) (fromContent c4) (fromContent c5) (fromContent c6) (fromContent c7) (fromContent c8) (fromContent c9) (fromContent c10) (fromContent c11) (fromContent c12) (fromContent c13)
                where [c1, c2, c3, c4, c5, c6, c7, c8, c9, c10, c11, c12, c13] = forFromContent nss ["urn0:LogMessage", "urn0:LogMessageChangeList", "urn0:LogMessageParty", "urn0:LogMessageBusinessSystem", "urn0:LogMessageBusinessComponent", "urn0:LogMessageIntegrationProcess", "urn0:LogMessageCommunicationChannel", "urn0:LogMessageSenderAgreement", "urn0:LogMessageReceiverAgreement", "urn0:LogMessageInterfaceDetermination", "urn0:LogMessageReceiverDetermination", "urn0:LogMessageValueMapping", "urn0:LogMessageConfigurationScenario"] cs

instance Xmlable LogMessage where
        toContent x = [makeToContent ["urn0:LogMessageItem"] [toContent.lmLM_Item] x]
        fromContent cs = LogMessage (fromContent c1)
                where [c1] = forFromContent nss ["urn0:LogMessageItem"] cs

instance Xmlable LM_Item where
        toContent x = [makeToContent ["urn0:SeverityCode", "urn0:ClassificationCode", "urn0:Message"] [toContent.lmiSeverityCode, toContent.lmiClassificationCode, toContent.lmiMessage] x]
        fromContent cs = LM_Item (fromContent c1) (fromContent c2) (fromContent c3)
                where [c1, c2, c3] = forFromContent nss ["urn0:SeverityCode", "urn0:ClassificationCode", "urn0:Message"] cs

instance Xmlable Text where
        toContent x = [makeToContent ["urn0:languageCode", "urn0:value"] [toContent.tlanguageCode, toContent.tvalue] x]
        fromContent cs = Text (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:languageCode", "urn0:value"] cs

instance Xmlable LM_ChangeList where
        toContent x = [makeToContent ["urn0:ChangeListID", "urn0:LogMessageItem"] [toContent.lmclCL_ID, toContent.lmclLM_Item] x]
        fromContent cs = LM_ChangeList (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:ChangeListID", "urn0:LogMessageItem"] cs

instance Xmlable CL_ID where
        toContent x = [makeToContent ["urn1:ChangeListID", "urn1:Name", "urn1:Description"] [toContent.clidCL_ID, toContent.clidName, toContent.clidDescription] x]
        fromContent cs = CL_ID (fromContent c1) (fromContent c2) (fromContent c3)
                where [c1, c2, c3] = forFromContent nss ["urn1:ChangeListID", "urn1:Name", "urn1:Description"] cs

instance Xmlable LONG_Description where
        toContent x = [makeToContent ["urn1:languageCode", "urn1:value"] [toContent.ldlanguageCode, toContent.ldvalue] x]
        fromContent cs = LONG_Description (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn1:languageCode", "urn1:value"] cs

instance Xmlable LM_Party where
        toContent x = [makeToContent ["urn0:PartyID", "urn0:LogMessageItem"] [toContent.lmpPI_D, toContent.lmpLM_Item] x]
        fromContent cs = LM_Party (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:PartyID", "urn0:LogMessageItem"] cs

instance Xmlable LM_CommunicationComponent where
        toContent x = [makeToContent ["urn0:CommunicationComponentID", "urn0:LogMessageItem"] [toContent.lmccmpCC_ID, toContent.lmccmpLM_Item] x]
        fromContent cs = LM_CommunicationComponent (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:CommunicationComponentID", "urn0:LogMessageItem"] cs

instance Xmlable Cmp_ID where
        toContent x = [makeToContent ["urn0:PartyID", "urn0:ComponentID"] [toContent.ccidPI_D, toContent.ccidCI_D] x]
        fromContent cs = Cmp_ID (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:PartyID", "urn0:ComponentID"] cs

instance Xmlable LM_CommunicationChannel where
        toContent x = [makeToContent ["urn0:CommunicationChannelID", "urn0:LogMessageItem"] [toContent.lmccCC_ID, toContent.lmccLM_Item] x]
        fromContent cs = LM_CommunicationChannel (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:CommunicationChannelID", "urn0:LogMessageItem"] cs

instance Xmlable CC_ID where
        toContent x = [makeToContent ["urn0:PartyID", "urn0:ComponentID", "urn0:ChannelID"] [toContent.ccidPartyID, toContent.ccidComponentID, toContent.ccidChannelID] x]
        fromContent cs = CC_ID (fromContent c1) (fromContent c2) (fromContent c3)
                where [c1, c2, c3] = forFromContent nss ["urn0:PartyID", "urn0:ComponentID", "urn0:ChannelID"] cs

instance Xmlable LM_MessageHeader where
        toContent x = [makeToContent ["urn0:MessageHeader", "urn0:LogMessageItem"] [toContent.lmmhMessageHeader, toContent.lmmhLM_Item] x]
        fromContent cs = LM_MessageHeader (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:MessageHeader", "urn0:LogMessageItem"] cs

instance Xmlable MH_ID where
        toContent x = [makeToContent ["urn0:SenderPartyID", "urn0:SenderComponentID", "urn0:InterfaceName", "urn0:InterfaceNamespace", "urn0:ReceiverPartyID", "urn0:ReceiverComponentID"] [toContent.mhidSP_ID, toContent.mhidSC_ID, toContent.mhidInterfaceName, toContent.mhidInterfaceNamespace, toContent.mhidRP_ID, toContent.mhidRC_ID] x]
        fromContent cs = MH_ID (fromContent c1) (fromContent c2) (fromContent c3) (fromContent c4) (fromContent c5) (fromContent c6)
                where [c1, c2, c3, c4, c5, c6] = forFromContent nss ["urn0:SenderPartyID", "urn0:SenderComponentID", "urn0:InterfaceName", "urn0:InterfaceNamespace", "urn0:ReceiverPartyID", "urn0:ReceiverComponentID"] cs

instance Xmlable LM_ValueMapping where
        toContent x = [makeToContent ["urn0:ValueMappingID", "urn0:LogMessageItem"] [toContent.lmvmVM_ID, toContent.lmvmLM_Item] x]
        fromContent cs = LM_ValueMapping (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:ValueMappingID", "urn0:LogMessageItem"] cs

instance Xmlable LM_ConfigurationScenario where
        toContent x = [makeToContent ["urn0:ConfigurationScenarioID", "urn0:LogMessageItem"] [toContent.lmcsCS_ID, toContent.lmcsLM_Item] x]
        fromContent cs = LM_ConfigurationScenario (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:ConfigurationScenarioID", "urn0:LogMessageItem"] cs

instance Xmlable CheckContentInput where
        toContent x = [makeToContent ["urn2:ChangeListCheckContentRequest"] [toContent.cL_CheckContentRequest] x]
        fromContent cs = CheckContentInput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:ChangeListCheckContentRequest"] cs

instance Xmlable CheckContentOutput where
        toContent x = [makeToContent ["urn2:Response"] [toContent.responseCheckContentOutput] x]
        fromContent cs = CheckContentOutput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:Response"] cs

instance Xmlable CreateInput where
        toContent x = [makeToContent ["urn2:ChangeListCreateRequest"] [toContent.cL_CreateRequest] x]
        fromContent cs = CreateInput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:ChangeListCreateRequest"] cs

instance Xmlable CL_IDRestricted where
        toContent x = [makeToContent ["urn1:Name", "urn1:Description"] [toContent.clidrName, toContent.clidrDescription] x]
        fromContent cs = CL_IDRestricted (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn1:Name", "urn1:Description"] cs

instance Xmlable CreateOutput where
        toContent x = [makeToContent ["urn2:Response"] [toContent.responseCreateOutput] x]
        fromContent cs = CreateOutput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:Response"] cs

instance Xmlable CL_CreateOut where
        toContent x = [makeToContent ["urn0:ChangeListID", "urn0:LogMessageCollection"] [toContent.clcoCL_ID, toContent.clcoLM_Collection] x]
        fromContent cs = CL_CreateOut (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:ChangeListID", "urn0:LogMessageCollection"] cs

instance Xmlable GetCacheStateInput where
        toContent x = [makeToContent ["urn2:ChangeListGetCacheStateRequest"] [toContent.cL_GetCacheStateRequest] x]
        fromContent cs = GetCacheStateInput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:ChangeListGetCacheStateRequest"] cs

instance Xmlable GetCacheStateOutput where
        toContent x = [makeToContent ["urn2:Response"] [toContent.responseGetCacheStateOutput] x]
        fromContent cs = GetCacheStateOutput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:Response"] cs

instance Xmlable CL_GetCacheStateOut where
        toContent x = [makeToContent ["urn0:CacheState", "urn0:LogMessageCollection"] [toContent.clgcsoCacheState, toContent.clgcsoLM_Collection] x]
        fromContent cs = CL_GetCacheStateOut (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:CacheState", "urn0:LogMessageCollection"] cs

instance Xmlable CL_CacheState where
        toContent x = [makeToContent ["urn0:Consumer", "urn0:NotificationState", "urn0:RefreshState", "urn0:ErrorMessage"] [toContent.clcsConsumer, toContent.clcsNotificationState, toContent.clcsRefreshState, toContent.clcsErrorMessage] x]
        fromContent cs = CL_CacheState (fromContent c1) (fromContent c2) (fromContent c3) (fromContent c4)
                where [c1, c2, c3, c4] = forFromContent nss ["urn0:Consumer", "urn0:NotificationState", "urn0:RefreshState", "urn0:ErrorMessage"] cs

instance Xmlable GetObjectIdentifiersInput where
        toContent x = [makeToContent ["urn2:ChangeListGetObjectIdentifiersRequest"] [toContent.cL_GetObjectIdentifiersRequest] x]
        fromContent cs = GetObjectIdentifiersInput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:ChangeListGetObjectIdentifiersRequest"] cs

instance Xmlable GetObjectIdentifiersOutput where
        toContent x = [makeToContent ["urn2:Response"] [toContent.responseGetObjectIdentifiersOutput] x]
        fromContent cs = GetObjectIdentifiersOutput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:Response"] cs

instance Xmlable CL_GetObjectIdentifiersOut where
        toContent x = [makeToContent ["urn0:PartyID", "urn0:BusinessSystemID", "urn0:BusinessComponentID", "urn0:IntegrationProcessID", "urn0:CommunicationChannelID", "urn0:SenderAgreementID", "urn0:ReceiverAgreementID", "urn0:ReceiverDeterminationID", "urn0:InterfaceDeterminationID", "urn0:ValueMappingID", "urn0:ConfigurationScenarioID", "urn0:LogMessageCollection"] [toContent.clgoioPI_D, toContent.clgoioBS_ID, toContent.clgoioBC_ID, toContent.clgoioIP_ID, toContent.clgoioCC_ID, toContent.clgoioSA_ID, toContent.clgoioRA_ID, toContent.clgoioRD_ID, toContent.clgoioID_ID, toContent.clgoioVM_ID, toContent.clgoioCS_ID, toContent.clgoioLM_Collection] x]
        fromContent cs = CL_GetObjectIdentifiersOut (fromContent c1) (fromContent c2) (fromContent c3) (fromContent c4) (fromContent c5) (fromContent c6) (fromContent c7) (fromContent c8) (fromContent c9) (fromContent c10) (fromContent c11) (fromContent c12)
                where [c1, c2, c3, c4, c5, c6, c7, c8, c9, c10, c11, c12] = forFromContent nss ["urn0:PartyID", "urn0:BusinessSystemID", "urn0:BusinessComponentID", "urn0:IntegrationProcessID", "urn0:CommunicationChannelID", "urn0:SenderAgreementID", "urn0:ReceiverAgreementID", "urn0:ReceiverDeterminationID", "urn0:InterfaceDeterminationID", "urn0:ValueMappingID", "urn0:ConfigurationScenarioID", "urn0:LogMessageCollection"] cs

instance Xmlable GetStateInput where
        toContent x = [makeToContent ["urn2:ChangeListGetStateRequest"] [toContent.cL_GetStateRequest] x]
        fromContent cs = GetStateInput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:ChangeListGetStateRequest"] cs

instance Xmlable GetStateOutput where
        toContent x = [makeToContent ["urn2:Response"] [toContent.responseGetStateOutput] x]
        fromContent cs = GetStateOutput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:Response"] cs

instance Xmlable CL_GetStateOut where
        toContent x = [makeToContent ["urn0:State", "urn0:LogMessageCollection"] [toContent.clgsoState, toContent.clgsoLM_Collection] x]
        fromContent cs = CL_GetStateOut (fromContent c1) (fromContent c2)
                where [c1, c2] = forFromContent nss ["urn0:State", "urn0:LogMessageCollection"] cs

instance Xmlable RevertInput where
        toContent x = [makeToContent ["urn2:ChangeListRevertRequest"] [toContent.cL_RevertRequest] x]
        fromContent cs = RevertInput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:ChangeListRevertRequest"] cs

instance Xmlable RevertOutput where
        toContent x = [makeToContent ["urn2:Response"] [toContent.responseRevertOutput] x]
        fromContent cs = RevertOutput (fromContent c1)
                where [c1] = forFromContent nss ["urn2:Response"] cs