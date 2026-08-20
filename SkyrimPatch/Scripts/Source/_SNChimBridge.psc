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

	String PromptLine
	If asNeed == "thirsty"
		If abNoSupplies
			PromptLine = "You are thirsty and have nothing to drink. Make one short in-character comment about needing water. Do not mention mods, meters, or game menus."
		Else
			PromptLine = "You are thirsty. Make one short in-character comment about needing a drink. Do not mention mods, meters, or game menus."
		EndIf
	ElseIf abNoSupplies
		PromptLine = "You are hungry and have no food. Make one short in-character comment about needing a meal. Do not mention mods, meters, or game menus."
	Else
		PromptLine = "You are hungry. Make one short in-character comment about needing food. Do not mention mods, meters, or game menus."
	EndIf

	AIAgentFunctions.requestMessageForActor(PromptLine, "chat", asName)
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
