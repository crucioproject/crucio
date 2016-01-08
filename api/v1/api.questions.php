<?php

$app->group('/questions', function() {

    $this->get('', function($request, $response, $args) {
		$mysql = init();
		$query_params = $request->getQueryParams();

		$subquery_array = explode(' ', $query_params['query']);
		$sql_query = "";
		for ($i = 0; $i < count($subquery_array); $i++) {
    		$sql_query .= "AND ( q.question LIKE :sub$i
    		    OR q.answers LIKE :sub$i
    		    OR q.explanation LIKE :sub$i ) ";
		}
		// $question_id = ( intval($query_params['query']) > 0) ? intval($query) : null; // Query is question id
        $limit = ($query_params['limit']) ? intval($query_params['limit']) : 10000;

		$stmt = $mysql->prepare(
		    "SELECT q.*, s.name AS 'subject', e.subject_id, e.semester
		    FROM questions q
		    INNER JOIN exams e ON q.exam_id = e.exam_id
		    INNER JOIN subjects s ON e.subject_id = s.subject_id
		    WHERE e.visibility = IFNULL(:visibility, e.visibility)
		        AND e.semester = IFNULL(:semester, e.semester)
		        AND e.subject_id = IFNULL(:subject_id, e.subject_id)
		        AND q.category_id = IFNULL(:category_id, q.category_id)
		        $sql_query
		        AND q.question_id = IFNULL(:question_id, q.question_id)
		    LIMIT :limit"
		);
        $stmt->bindValue(':visibility', $query_params['visibility']);
        $stmt->bindValue(':semester', $query_params['semester']);
        $stmt->bindValue(':subject_id', $query_params['subject_id'], PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $query_params['category_id'], PDO::PARAM_INT);
        for ($i = 0; $i < count($subquery_array); $i++) {
            $stmt->bindValue(":sub$i", '%'.$subquery_array[$i].'%');
        }
        $stmt->bindValue(':question_id', $question_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

		$data['result'] = getAll($stmt);
		return createResponse($response, $data);
	});


	$this->get('/count', function($request, $response, $args) {
		$mysql = init();
		$query_params = $request->getQueryParams();

		$selection = json_decode($query_params['selection']);

		$sql = '0 = 1 ';
		$sql_params = [];
		foreach ($selection as $subject_id => $entry) {
    		if ($entry->subject) {
                $sql .= 'OR e.subject_id = ? ';
                array_push($sql_params, $subject_id);
    		}
    		foreach ($entry->categories as $category_id => $data_category) {
                if ($data_category) {
                    $sql .= 'OR q.category_id = ? ';
                    array_push($sql_params, $category_id);
                }
            }
        }

		$stmt = $mysql->prepare(
		    "SELECT COUNT(*) as 'c'
		    FROM questions q
		    INNER JOIN exams e ON e.exam_id = q.exam_id
		    WHERE $sql "
		);

		$data['count'] = getFetch($stmt, $sql_params)['c'];
	    return createResponse($response, $data);
	});

	$this->get('/prepare-subjects', function($request, $response, $args) {
        $mysql = init();
		$query_params = $request->getQueryParams();

		$selection = json_decode($query_params['selection']);
		$limit = $query_params['limit'] ? intval($query_params['limit']) : 10000;

		$sql = '0 = 1 ';
		$sql_params = [];
		foreach ($selection as $subject_id => $entry) {
    		if ($entry->subject) {
                $sql .= 'OR e.subject_id = ? ';
                array_push($sql_params, $subject_id);
    		}
    		foreach ($entry->categories as $category_id => $data_category) {
                if ($data_category) {
                    $sql .= 'OR q.category_id = ? ';
                    array_push($sql_params, $category_id);
                }
            }
        }

		$stmt = $mysql->prepare(
		    "SELECT q.*
		    FROM questions q
		    INNER JOIN exams e ON e.exam_id = q.exam_id
		    WHERE $sql
		    ORDER BY rand()
		    LIMIT :limit"
		);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $list = getAll($stmt, $sql_params);
        foreach ($list as &$question) {
            $question['answers'] = unserialize($question['answers']);
        }

		$data['list'] = $list;
	    return createResponse($response, $data);
    });


	$this->get('/{question_id}', function($request, $response, $args) {
		$mysql = init();

		$stmt_question = $mysql->prepare(
		    "SELECT q.*, e.*, u.email, u.username
            FROM questions q
            INNER JOIN exams e ON e.exam_id = q.exam_id
            INNER JOIN users u ON u.user_id = e.user_id_added
            WHERE q.question_id = :question_id"
		);
		$stmt_question->bindValue(':question_id', $args['question_id'], PDO::PARAM_INT);
        $question = getFetch($stmt_question);
		$question['answers'] = unserialize($question['answers']);

		$stmt_comments = $mysql->prepare(
		    "SELECT *
            FROM comments
            WHERE question_id = :question_id
            ORDER BY comment_id ASC"
        );
        $stmt_comments->bindValue(':question_id', $args['question_id'], PDO::PARAM_INT);
        $comments = getAll($stmt_comments);

		$data['question'] = $question;
		$data['comments'] = $comments;
		return createResponse($response, $data);
	});

	$this->get('/{question_id}/user/{user_id}', function($request, $response, $args) {
		$mysql = init();

		$stmt_question = $mysql->prepare(
		    "SELECT q.*, e.*
            FROM questions q
            INNER JOIN exams e ON e.exam_id = q.exam_id
		    WHERE q.question_id = :question_id"
		);
		$stmt_question->bindValue(':question_id', $args['question_id'], PDO::PARAM_INT);
        $question = getFetch($stmt_question);
		$question['answers'] = unserialize($question['answers']);

		$stmt_tags = $mysql->prepare(
		    "SELECT t.tags
            FROM tags t
            WHERE t.user_id = :user_id
                AND t.question_id = :question_id"
		);
		$stmt_tags->bindValue(':user_id', intval($args['user_id']), PDO::PARAM_INT);
		$stmt_tags->bindValue(':question_id', intval($args['question_id']), PDO::PARAM_INT);
		$tags = getFetch($stmt_tags);
		if (!$tags) {
			$tags = '';
        }

        $stmt_comments = $mysql->prepare(
		    "SELECT c.*, u.username, SUM(IF(uc.user_id != :user_id, uc.user_voting, 0)) as 'voting', SUM(IF(uc.user_id = :user_id, uc.user_voting, 0)) as 'user_voting'
            FROM comments c
            INNER JOIN users u ON c.user_id = u.user_id
            LEFT JOIN user_comments_data uc ON uc.comment_id = c.comment_id
            WHERE c.question_id = :question_id
            GROUP BY c.comment_id
            ORDER BY c.comment_id ASC"
        );
        $stmt_comments->bindValue(':user_id', $args['user_id'], PDO::PARAM_INT);
        $stmt_comments->bindValue(':question_id', $args['question_id'], PDO::PARAM_INT);
        $comments = getAll($stmt_comments);

		$data['question'] = $question;
		$data['tags'] = $tags['tags'];
		$data['comments'] = $comments;
		return createResponse($response, $data);
	});


	$this->post('', function($request, $response, $args) {
    	$mysql = init();
		$body = $request->getParsedBody();

		$stmt = $mysql->prepare(
    		"INSERT INTO questions (question, answers, correct_answer, exam_id, date_added,
    		    user_id_added, explanation, question_image_url, type, category_id)
		    VALUES (:question, :answers, :correct_answer, :exam_id, :date, :user_id_added,
		        :explanation, :question_image_url, :type, :category_id)"
        );
        $stmt->bindValue(':question', $body['question']);
        $stmt->bindValue(':answers', serialize($body['answers']));
        $stmt->bindValue(':correct_answer', $body['correct_answer']);
        $stmt->bindValue(':exam_id', $body['exam_id'], PDO::PARAM_INT);
        $stmt->bindValue(':date', time());
        $stmt->bindValue(':user_id_added', $body['user_id_added'], PDO::PARAM_INT);
        $stmt->bindValue(':explanation', $body['explanation']);
        $stmt->bindValue(':question_image_url', $body['question_image_url']);
        $stmt->bindValue(':type', $args['type']);
        $stmt->bindValue(':category_id', $args['category_id']);

        $data['status'] = execute($stmt);
        $data['question_id'] = $mysql->lastInsertId();
		return createResponse($response, $data);
	});


	$this->put('/{question_id}', function($request, $response, $args) {
    	$mysql = init();
		$body = $request->getParsedBody();

		$stmt = $mysql->prepare(
    		"UPDATE questions
    		SET question = :question, answers = :answers, correct_answer = :correct_answer,
    		    exam_id = :exam_id, explanation = :explanation,
    		    question_image_url = :question_image_url, type = :type, category_id = :category_id
            WHERE question_id = :question_id"
        );
        $stmt->bindValue(':question', $body['question']);
        $stmt->bindValue(':answers', serialize($body['answers']));
        $stmt->bindValue(':correct_answer', $body['correct_answer']);
        $stmt->bindValue(':exam_id', $body['exam_id'], PDO::PARAM_INT);
        $stmt->bindValue(':explanation', $body['explanation']);
        $stmt->bindValue(':question_image_url', $body['question_image_url']);
        $stmt->bindValue(':type', $body['type']);
        $stmt->bindValue(':category_id', $body['category_id'], PDO::PARAM_INT);
        $stmt->bindValue(':question_id', $args['question_id'], PDO::PARAM_INT);

        $data['status'] = execute($stmt);
		return createResponse($response, $data);
	});


	$this->delete('/{question_id}', function($request, $response, $args) {
		$mysql = init();

		$stmt = $mysql->prepare(
    		"DELETE
    		FROM questions
    		WHERE question_id = :question_id"
        );
        $stmt->bindValue(':question_id', $args['question_id'], PDO::PARAM_INT);

        $data['status'] = execute($stmt);
		return createResponse($response, $data);
	});
});

?>