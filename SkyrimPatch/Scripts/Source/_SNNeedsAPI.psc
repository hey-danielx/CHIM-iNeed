Scriptname _SNNeedsAPI
{NPC need markers for CHIM and other mods.}

;====================================================================================
; Player needs
;====================================================================================

Float Function GetHungerState(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.HungerState
	EndIf
	Return 0.0
EndFunction

Float Function GetThirstState(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.ThirstState
	EndIf
	Return 0.0
EndFunction

Float Function GetFatigueState(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.FatigueState
	EndIf
	Return 0.0
EndFunction

Int Function GetHungerPenalty(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetHungerPenaltyInt()
	EndIf
	Return 0
EndFunction

Int Function GetThirstPenalty(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetThirstPenaltyInt()
	EndIf
	Return 0
EndFunction

Int Function GetFatiguePenalty(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetFatiguePenaltyInt()
	EndIf
	Return 0
EndFunction

String Function GetHungerLabel(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetHungerLabel()
	EndIf
	Return ""
EndFunction

String Function GetThirstLabel(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetThirstLabel()
	EndIf
	Return ""
EndFunction

String Function GetFatigueLabel(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetFatigueLabel()
	EndIf
	Return ""
EndFunction

String Function GetPlayerNeedsSummary(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetPlayerNeedsSummary()
	EndIf
	Return ""
EndFunction

Bool Function IsPlayerHungry(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetHungerPenaltyInt() >= 0 && akQuest.HungerRate > 0.0
	EndIf
	Return False
EndFunction

Bool Function IsPlayerThirsty(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetThirstPenaltyInt() >= 0 && akQuest.ThirstRate > 0.0
	EndIf
	Return False
EndFunction

; Penalty legend (player):
;  -2 well sated / hydrated / rested
;  -1 sated / hydrated / rested
;   0 a bit hungry / thirsty / tired
;   1 mild
;   2 moderate
;   3 severe

;====================================================================================
; Follower needs
;====================================================================================

Bool Function AreFollowerNeedsEnabled(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.AreFollowerNeedsEnabled()
	EndIf
	Return False
EndFunction

Int Function GetFollowerNeedsType(_SNQuestScript akQuest) Global
	If akQuest
		Return akQuest.GetFollowerNeedsType()
	EndIf
	Return 0
EndFunction

Bool Function IsActorHungry(Actor akActor, Faction akHungryFaction) Global
	If akActor && akHungryFaction
		Return akActor.IsInFaction(akHungryFaction)
	EndIf
	Return False
EndFunction

Bool Function IsActorThirsty(Actor akActor, Faction akThirstyFaction) Global
	If akActor && akThirstyFaction
		Return akActor.IsInFaction(akThirstyFaction)
	EndIf
	Return False
EndFunction

String Function DescribeFollowerNeed(String asNeed, Bool abNoSupplies) Global
	If asNeed == "thirsty"
		If abNoSupplies
			Return "thirsty and has nothing to drink"
		EndIf
		Return "thirsty"
	EndIf
	If abNoSupplies
		Return "hungry and has no food"
	EndIf
	Return "hungry"
EndFunction
