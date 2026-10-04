# SmartJudge

Asks an AI model yes/no questions about things a consumer describes, and turns the answers into decisions. It knows nothing about the consumer's domain: the consumer describes its things and writes its questions.

## Language

### What is judged

**Subject**:
One thing the consumer wants judged, identified by the consumer's own key and described by its facts. A pair of things compared with each other is one subject.
_Avoid_: Item, record, state, entry

**Facts**:
The description of a subject in words, as named values the model reads. Never raw ids, amounts or dates the model cannot reason about.
_Avoid_: State, attributes, payload

**Context**:
Facts that hold for every subject in one request, sent once instead of being copied into each subject.
_Avoid_: Shared state, globals

### What is asked

**Question**:
A yes/no question about one subject, built from the consumer's text with the subject filled in, together with what "yes" and what "no" mean.
_Avoid_: Prompt, query

**Criteria**:
The two descriptions of a question that tell the model what counts as "yes" and what counts as "no".
_Avoid_: Rubric, definition

**Probability**:
The model's answer to one question: how likely "yes" is, between 0 and 1.
_Avoid_: Score, confidence, answer

### Who answers

**Judge**:
What a consumer asks: it takes subjects and questions, and gives back a probability per subject and question, keyed by the consumer's own keys.
_Avoid_: Asker, client, service

**Driver**:
One AI model behind the judge, reached through its provider; it answers one request at a time and has a name that says which model answered.
_Avoid_: Provider, client, backend, model

**Unavailable**:
The state of a driver that gave no usable answer, so the consumer falls back to its own way of deciding. A consumer's mistake in its questions is never "unavailable".
_Avoid_: Down, error, failure

### What is decided

**Rule**:
How the probabilities of a subject's questions become a verdict. Flag and choice are the rules SmartJudge ships; a consumer may add its own.
_Avoid_: Dimension, strategy, policy

**Verdict**:
The outcome of a rule for one subject: the decided value, its probability, and the driver that answered.
_Avoid_: Label, result, judgement

**Flag**:
A decision rule with one question: "yes" when its probability reaches the threshold, otherwise "no".
_Avoid_: Boolean, toggle

**Choice**:
A decision rule with one question per option: the most likely option wins, the first listed wins ties, and none wins below the threshold.
_Avoid_: Category, classification, enum

**Option**:
One possible outcome of a choice, with the question that asks whether it applies.
_Avoid_: Value, class, bucket

**Fingerprint**:
The identity of a set of questions and the judge that answers them; when it changes, earlier decisions are stale.
_Avoid_: Version, hash, checksum
