import pytest
from app import app, db, Student, Teacher, Course, Grade


@pytest.fixture
def client():
    app.config['TESTING'] = True
    app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///:memory:'
    app.config['WTF_CSRF_ENABLED'] = False
    with app.app_context():
        db.create_all()
        yield app.test_client()
        db.drop_all()


# ---- Index ----
def test_index(client):
    rv = client.get('/')
    assert rv.status_code == 200
    assert '学校管理系统'.encode() in rv.data


# ---- Students ----
def test_students_list_empty(client):
    rv = client.get('/students')
    assert rv.status_code == 200
    assert '暂无学生数据'.encode() in rv.data


def test_add_student(client):
    rv = client.post('/students/add', data={
        'student_id': 'S001', 'name': '张三', 'gender': '男', 'age': '18'
    }, follow_redirects=True)
    assert rv.status_code == 200
    assert '张三'.encode() in rv.data


def test_add_student_duplicate_id(client):
    client.post('/students/add', data={
        'student_id': 'S001', 'name': '张三', 'gender': '男', 'age': '18'
    })
    rv = client.post('/students/add', data={
        'student_id': 'S001', 'name': '李四', 'gender': '女', 'age': '19'
    }, follow_redirects=True)
    assert '学号已存在'.encode() in rv.data


def test_edit_student(client):
    client.post('/students/add', data={
        'student_id': 'S001', 'name': '张三', 'gender': '男', 'age': '18'
    })
    with app.app_context():
        student = Student.query.first()
        sid = student.id
    rv = client.post(f'/students/edit/{sid}', data={
        'student_id': 'S001', 'name': '张三改', 'gender': '男', 'age': '20'
    }, follow_redirects=True)
    assert '张三改'.encode() in rv.data


def test_delete_student(client):
    client.post('/students/add', data={
        'student_id': 'S001', 'name': '张三', 'gender': '男', 'age': '18'
    })
    with app.app_context():
        sid = Student.query.first().id
    rv = client.post(f'/students/delete/{sid}', follow_redirects=True)
    assert rv.status_code == 200
    assert '暂无学生数据'.encode() in rv.data


# ---- Teachers ----
def test_add_teacher(client):
    rv = client.post('/teachers/add', data={
        'teacher_id': 'T001', 'name': '王老师', 'gender': '女', 'subject': '数学'
    }, follow_redirects=True)
    assert '王老师'.encode() in rv.data


def test_add_teacher_duplicate_id(client):
    client.post('/teachers/add', data={
        'teacher_id': 'T001', 'name': '王老师', 'gender': '女', 'subject': '数学'
    })
    rv = client.post('/teachers/add', data={
        'teacher_id': 'T001', 'name': '李老师', 'gender': '男', 'subject': '语文'
    }, follow_redirects=True)
    assert '工号已存在'.encode() in rv.data


def test_delete_teacher(client):
    client.post('/teachers/add', data={
        'teacher_id': 'T001', 'name': '王老师', 'gender': '女', 'subject': '数学'
    })
    with app.app_context():
        tid = Teacher.query.first().id
    rv = client.post(f'/teachers/delete/{tid}', follow_redirects=True)
    assert rv.status_code == 200
    assert '暂无教师数据'.encode() in rv.data


# ---- Courses ----
def test_add_course(client):
    client.post('/teachers/add', data={
        'teacher_id': 'T001', 'name': '王老师', 'gender': '女', 'subject': '数学'
    })
    with app.app_context():
        tid = Teacher.query.first().id
    rv = client.post('/courses/add', data={
        'course_id': 'C001', 'name': '高等数学', 'teacher_id': str(tid)
    }, follow_redirects=True)
    assert '高等数学'.encode() in rv.data


def test_add_course_duplicate_id(client):
    client.post('/courses/add', data={
        'course_id': 'C001', 'name': '高等数学', 'teacher_id': ''
    })
    rv = client.post('/courses/add', data={
        'course_id': 'C001', 'name': '线性代数', 'teacher_id': ''
    }, follow_redirects=True)
    assert '课程号已存在'.encode() in rv.data


# ---- Grades ----
def test_add_grade(client):
    client.post('/students/add', data={
        'student_id': 'S001', 'name': '张三', 'gender': '男', 'age': '18'
    })
    client.post('/courses/add', data={
        'course_id': 'C001', 'name': '高等数学', 'teacher_id': ''
    })
    with app.app_context():
        sid = Student.query.first().id
        cid = Course.query.first().id
    rv = client.post('/grades/add', data={
        'student_id': str(sid), 'course_id': str(cid), 'score': '95.5'
    }, follow_redirects=True)
    assert '张三'.encode() in rv.data
    assert '95.5'.encode() in rv.data


def test_add_grade_invalid_score(client):
    client.post('/students/add', data={
        'student_id': 'S001', 'name': '张三', 'gender': '男', 'age': '18'
    })
    client.post('/courses/add', data={
        'course_id': 'C001', 'name': '高等数学', 'teacher_id': ''
    })
    with app.app_context():
        sid = Student.query.first().id
        cid = Course.query.first().id
    rv = client.post('/grades/add', data={
        'student_id': str(sid), 'course_id': str(cid), 'score': '150'
    }, follow_redirects=True)
    assert '成绩必须在0到100之间'.encode() in rv.data
