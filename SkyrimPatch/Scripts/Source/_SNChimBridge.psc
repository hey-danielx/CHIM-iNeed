Scriptname _SNChimBridge
{NPC only CHIM bridge. Player hunger is left to the human.}

Bool Function IsCHIMLoaded() Global
	Return Game.GetModByName("AIAgent.esp") != 255
EndFunction

Function SetNeedMarker(_SNQuestScript akQuest, Actor akFollower, String asName, String asNeed, Bool abNoSupplies) Global
	If !akQuest || !akFollower || asName == ""
		Return
	EndIf
	If !akQuest.AreFollowerNeedsEnabled()
		Return
	EndIf

	akQuest.SendModEvent("_SN_FollowerNeedMarker", asName + "|" + asNeed)
	If !IsCHIMLoaded()
		Return
	EndIf

	String StateLine = asName + " is " + _SNNeedsAPI.DescribeFollowerNeed(asNeed, abNoSupplies) + "."
	AIAgentFunctions.logMessageForActor(StateLine, "infoaction", asName)
	String Flag = "0"
	If abNoSupplies
		Flag = "1"
	EndIf
	AIAgentFunctions.logMessageForActor("ineed_state@" + asName + "@" + asNeed + "@" + Flag, "infoaction", asName)
EndFunction

Function ClearNeedMarker(_SNQuestScript akQuest, String asName, String asNeed) Global
	If !akQuest || asName == "" || asNeed == ""
		Return
	EndIf

	akQuest.SendModEvent("_SN_FollowerNeedCleared", asName + "|" + asNeed)
	If !IsCHIMLoaded()
		Return
	EndIf

	String StateLine
	If asNeed == "thirsty"
		StateLine = asName + " is no longer thirsty."
	Else
		StateLine = asName + " is no longer hungry."
	EndIf
	AIAgentFunctions.logMessageForActor(StateLine, "infoaction", asName)
	AIAgentFunctions.logMessageForActor("ineed_state@" + asName + "@" + asNeed + "@clear", "infoaction", asName)
EndFunction

String Function NeedCommentPrompt(String asNeed, Bool abNoSupplies, Bool abBored) Global
	String Prefix = ""
	If abBored
		Prefix = "You have a quiet moment. "
	EndIf
	If asNeed == "thirsty"
		If abNoSupplies
			Return Prefix + "You are thirsty and have nothing to drink. Make one short in-character comment about needing water. Do not mention mods, meters, or game menus."
		EndIf
		Return Prefix + "You are thirsty. Make one short in-character comment about needing a drink. Do not mention mods, meters, or game menus."
	EndIf
	If abNoSupplies
		Return Prefix + "You are hungry and have no food. Make one short in-character comment about needing a meal. Do not mention mods, meters, or game menus."
	EndIf
	Return Prefix + "You are hungry. Make one short in-character comment about needing food. Do not mention mods, meters, or game menus."
EndFunction

Function TriggerNeedComment(_SNQuestScript akQuest, Actor akFollower, String asName, String asNeed, Bool abNoSupplies) Global
	If !akQuest || !akFollower || asName == ""
		Return
	EndIf
	If !akQuest.AreFollowerNeedsEnabled()
		Return
	EndIf
	If !akQuest.ChimNeedCommentReady(asName, asNeed)
		Return
	EndIf

	akQuest.ChimMarkNeedComment(asName, asNeed)
	akQuest.SendModEvent("_SN_FollowerNeed", asName + "|" + asNeed)
	If !IsCHIMLoaded()
		Return
	EndIf

	AIAgentFunctions.requestMessageForActor(NeedCommentPrompt(asNeed, abNoSupplies, false), "chat", asName)
EndFunction

Function TriggerNeedBoredComment(_SNQuestScript akQuest, Actor akFollower, String asName, String asNeed, Bool abNoSupplies) Global
	If !akQuest || !akFollower || asName == ""
		Return
	EndIf
	If !akQuest.AreFollowerNeedsEnabled()
		Return
	EndIf
	If !akQuest.ChimBoredRepeatReady(asName, asNeed)
		Return
	EndIf
	If !IsCHIMLoaded()
		Return
	EndIf
	If AIAgentFunctions.isActorTalking(asName) != 0
		Return
	EndIf

	akQuest.ChimMarkBoredRepeat(asName, asNeed)
	AIAgentFunctions.logMessageForActor("ineed_state@" + asName + "@" + asNeed + "@1", "infoaction", asName)
	AIAgentFunctions.requestMessageForActor(NeedCommentPrompt(asNeed, abNoSupplies, true), "bored", asName)
EndFunction

Function NotifyFollowerNeed(_SNQuestScript akQuest, Actor akFollower, String asName, String asNeed, Bool abNoSupplies) Global
	SetNeedMarker(akQuest, akFollower, asName, asNeed, abNoSupplies)
	TriggerNeedComment(akQuest, akFollower, asName, asNeed, abNoSupplies)
EndFunction

Function ReportCommandResult(String asNpcName, String asCommand, String asParameter, String asResult) Global
	If asNpcName == "" || !IsCHIMLoaded()
		Return
	EndIf
	AIAgentFunctions.logMessageForActor("command@" + asCommand + "@" + asParameter + "@" + asResult, "funcret", asNpcName)
EndFunction
